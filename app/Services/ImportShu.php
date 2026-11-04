<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\LogAudit;
use App\Models\Shu;
use App\Support\Nba;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor SHU per tahun buku (BR-011). Baris tidak valid dilaporkan per baris dan
 * tidak menghentikan baris valid lain; impor ulang memperbarui baris yang sama.
 */
final class ImportShu
{
    public const KOLOM = ['no_anggota', 'balas_jasa_saham', 'balas_jasa_pinjaman'];

    private const BATAS_NOMINAL = 9999999999999.99;

    /**
     * @return array{dibuat: int, diperbarui: int, galat: list<array{baris: int, pesan: string}>}
     */
    public function jalankan(string $berkas, int $tahunBuku): array
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

            if ($index !== false) {
                $posisi[$kolom] = $index;
            }
        }

        if (array_diff(self::KOLOM, array_keys($posisi)) !== []) {
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

            $anggota = Anggota::query()->where('no_anggota', $nba)->first();

            if ($anggota === null) {
                $hasil['galat'][] = ['baris' => $nomor, 'pesan' => "NBA {$nba} belum ada di master anggota. Impor anggota lebih dulu."];

                continue;
            }

            [$saham, $galatSaham] = $this->nominal($row[$posisi['balas_jasa_saham']] ?? null, 'balas_jasa_saham');
            [$pinjaman, $galatPinjaman] = $this->nominal($row[$posisi['balas_jasa_pinjaman']] ?? null, 'balas_jasa_pinjaman');

            if ($galatSaham !== null || $galatPinjaman !== null) {
                $hasil['galat'][] = ['baris' => $nomor, 'pesan' => (string) ($galatSaham ?? $galatPinjaman)];

                continue;
            }

            $shu = Shu::firstOrNew(['anggota_id' => $anggota->id, 'tahun_buku' => $tahunBuku]);
            $baru = ! $shu->exists;
            $sebelum = $baru ? null : $shu->only(['balas_jasa_saham', 'balas_jasa_pinjaman']);

            $shu->balas_jasa_saham = $saham;
            $shu->balas_jasa_pinjaman = $pinjaman;

            if (! $baru && ! $shu->isDirty()) {
                continue;
            }

            $shu->diubah_oleh = auth()->id();
            $shu->save();

            LogAudit::catat(
                $baru ? 'buat' : 'ubah',
                'shu',
                $shu->id,
                $sebelum,
                $shu->only(['balas_jasa_saham', 'balas_jasa_pinjaman']),
                'impor XLSX tahun buku '.$tahunBuku
            );

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

            return [null, "NBA terbaca sebagai angka {$teks} (".strlen($teks).' digit). Format kolom no_anggota sebagai Teks 15 digit, lalu unggah ulang.'];
        }

        $nba = Nba::normalize($nilai);

        return $nba === null
            ? [null, "NBA harus tepat 15 digit angka tanpa titik; ditemukan \"{$nilai}\"."]
            : [$nba, null];
    }

    /**
     * @return array{0: ?float, 1: ?string}
     */
    private function nominal(mixed $nilai, string $kolom): array
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return [0.0, null];
        }

        if (! is_numeric($nilai)) {
            return [null, "Kolom {$kolom} harus angka; ditemukan \"{$nilai}\"."];
        }

        $angka = (float) $nilai;

        if ($angka < 0 || $angka > self::BATAS_NOMINAL) {
            return [null, "Kolom {$kolom} harus antara 0 dan ".number_format(self::BATAS_NOMINAL, 0, ',', '.').'.'];
        }

        return [round($angka, 2), null];
    }
}
