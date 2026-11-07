<?php

namespace App\Services;

use App\Http\Requests\AnggotaRequest;
use App\Models\Anggota;
use App\Models\LogAudit;
use App\Support\Nba;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Impor XLSX tahunan (FR-001, D-009, O-007 = XLSX).
 * Baris tidak valid dilaporkan per baris dan tidak menghentikan baris valid lain.
 */
final class ImportAnggota
{
    public const KOLOM = [
        'no_anggota', 'nama', 'no_hp', 'email', 'alamat', 'jenis_anggota',
        'status_anggota', 'tgl_ja', 'tgl_keluar', 'simpanan_wajib', 'kolektibilitas', 'pinjaman_lancar',
    ];

    /**
     * @return array{dibuat: int, diperbarui: int, galat: list<array{baris: int, pesan: string}>}
     */
    public function jalankan(string $berkas): array
    {
        $reader = IOFactory::createReaderForFile($berkas);
        $reader->setReadDataOnly(true);
        $baris = $reader->load($berkas)->getActiveSheet()->toArray(null, true, false, false);

        $kepala = array_map(
            fn ($sel) => strtolower(trim(str_replace(' ', '_', (string) $sel))),
            array_shift($baris) ?? []
        );

        $posisi = [];

        foreach (self::KOLOM as $kolom) {
            $index = array_search($kolom, $kepala, true);

            if ($index === false) {
                continue;
            }

            $posisi[$kolom] = $index;
        }

        if (array_diff(['no_anggota', 'nama', 'tgl_ja', 'simpanan_wajib'], array_keys($posisi)) !== []) {
            return [
                'dibuat' => 0,
                'diperbarui' => 0,
                'galat' => [['baris' => 1, 'pesan' => 'Baris judul wajib memuat kolom: '.implode(', ', self::KOLOM).'.']],
            ];
        }

        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'galat' => []];
        $sudahDibaca = [];

        foreach ($baris as $i => $row) {
            $nomor = $i + 2;

            if (array_filter($row, fn ($sel) => $sel !== null && trim((string) $sel) !== '') === []) {
                continue;
            }

            [$nba, $galatNba] = $this->nba($row[$posisi['no_anggota']] ?? null);

            if ($galatNba !== null) {
                $hasil['galat'][] = ['baris' => $nomor, 'pesan' => $galatNba];

                continue;
            }

            if (isset($sudahDibaca[$nba])) {
                $hasil['galat'][] = ['baris' => $nomor, 'pesan' => "NBA {$nba} duplikat di dalam berkas (sudah ada pada baris {$sudahDibaca[$nba]})."];

                continue;
            }

            $sudahDibaca[$nba] = $nomor;

            $data = ['no_anggota' => $nba];

            foreach ($posisi as $kolom => $index) {
                if ($kolom === 'no_anggota') {
                    continue;
                }

                $data[$kolom] = $row[$index] ?? null;
            }

            $data['tgl_ja'] = $this->tanggal($data['tgl_ja'] ?? null);
            $data['tgl_keluar'] = $this->tanggal($data['tgl_keluar'] ?? null);
            $data['no_hp'] = AnggotaRequest::normalisasiNomorHp($data['no_hp'] ?? null);
            $data['jenis_anggota'] = $this->pilihan($data['jenis_anggota'] ?? '') ?: 'biasa';
            $data['status_anggota'] = $this->pilihan($data['status_anggota'] ?? '') ?: 'aktif';
            $data['pinjaman_lancar'] = $this->boolean($data['pinjaman_lancar'] ?? null);
            $data['kolektibilitas'] = is_numeric($data['kolektibilitas'] ?? null) ? (int) $data['kolektibilitas'] : $data['kolektibilitas'];

            $validator = Validator::make(
                $data,
                AnggotaRequest::aturan(izinkanNbaAda: true),
                AnggotaRequest::pesan(),
            );

            if ($validator->fails()) {
                $hasil['galat'][] = ['baris' => $nomor, 'pesan' => implode(' ', $validator->errors()->all())];

                continue;
            }

            $bersih = $validator->validated();
            $anggota = Anggota::firstOrNew(['no_anggota' => $bersih['no_anggota']]);
            $baru = ! $anggota->exists;
            $sebelum = $baru ? null : $anggota->only(array_keys($bersih));

            $anggota->fill($bersih);

            if (! $baru && ! $anggota->isDirty()) {
                continue;
            }

            $anggota->save();
            LogAudit::catat($baru ? 'buat' : 'ubah', 'anggota', $anggota->id, $sebelum, $anggota->only(array_keys($bersih)), 'impor XLSX');
            $hasil[$baru ? 'dibuat' : 'diperbarui']++;
        }

        return $hasil;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function nba(mixed $nilai): array
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return [null, 'NBA kosong.'];
        }

        if (! is_string($nilai)) {
            $teks = sprintf('%.0f', (float) $nilai);

            if (preg_match('/^[0-9]{15}$/', $teks) === 1) {
                return [$teks, null];
            }

            // O-007: Excel membuang nol awal; jangan menambal nomor secara otomatis.
            return [null, "NBA terbaca sebagai angka {$teks} (".strlen($teks).' digit). Format kolom no_anggota sebagai Teks 15 digit, lalu unggah ulang.'];
        }

        $nba = Nba::normalize($nilai);

        return $nba === null
            ? [null, "NBA harus tepat 15 digit angka tanpa titik; ditemukan \"{$nilai}\"."]
            : [$nba, null];
    }

    private function tanggal(mixed $nilai): mixed
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        if (is_int($nilai) || is_float($nilai)) {
            return Date::excelToDateTimeObject($nilai)->format('Y-m-d');
        }

        $teks = trim((string) $nilai);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $waktu = \DateTime::createFromFormat('!'.$format, $teks);

            if ($waktu !== false && $waktu->format($format) === $teks) {
                return $waktu->format('Y-m-d');
            }
        }

        return $teks;
    }

    private function pilihan(mixed $nilai): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $nilai)));
    }

    private function boolean(mixed $nilai): ?bool
    {
        $teks = strtolower(trim((string) $nilai));

        return $teks === '' ? null : in_array($teks, ['1', 'ya', 'y', 'true', 'lancar'], true);
    }
}
