<?php

namespace App\Services;

use App\Models\DoorprizeHadiah;
use App\Models\DoorprizePemenang;
use App\Models\KasScanner;
use App\Models\LogUndangan;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\Shu;

/**
 * FR-011: satu tempat menghitung angka laporan supaya halaman dan ekspor XLSX
 * tidak pernah berbeda. Bagian per role mengikuti ROLE_PERMISSION_MATRIX
 * ("Laporan semua": Admin semua, Keuangan kas & transport, Helpdesk terbatas).
 */
final class Laporan
{
    public const SEMUA_BAGIAN = ['peserta', 'undangan', 'presensi', 'kas', 'kuorum', 'shu', 'doorprize'];

    private const AKSES = [
        'admin' => self::SEMUA_BAGIAN,
        'keuangan' => ['presensi', 'kas', 'kuorum'],
        'helpdesk' => ['peserta', 'undangan'],
    ];

    /**
     * @return array<int, string>
     */
    public function bagian(string $role): array
    {
        return self::AKSES[$role] ?? [];
    }

    /**
     * @param  array<int, string>  $bagian
     * @return array<string, mixed>
     */
    public function rekap(Rapat $rapat, array $bagian): array
    {
        $rekap = [];

        foreach ($bagian as $nama) {
            $rekap[$nama] = match ($nama) {
                'peserta' => $this->peserta($rapat),
                'undangan' => $this->undangan($rapat),
                'presensi' => $this->presensi($rapat),
                'kas' => $this->kas($rapat),
                'kuorum' => (new Kuorum)->hitung($rapat),
                'shu' => $this->shu(),
                'doorprize' => $this->doorprize($rapat),
            };
        }

        return $rekap;
    }

    /**
     * @return array<string, int>
     */
    private function peserta(Rapat $rapat): array
    {
        $dasar = Peserta::query()->where('rapat_id', $rapat->id);

        return [
            'total' => (clone $dasar)->count(),
            'layak' => (clone $dasar)->where('hasil_kelayakan', Peserta::LAYAK)->count(),
            'tidak_layak' => (clone $dasar)->where('hasil_kelayakan', Peserta::TIDAK_LAYAK)->count(),
            'verified' => (clone $dasar)->where('status_verifikasi', Peserta::VERIFIED)->count(),
            'rejected' => (clone $dasar)->where('status_verifikasi', Peserta::REJECTED)->count(),
            'pending' => (clone $dasar)->where('status_verifikasi', Peserta::PENDING)->count(),
        ];
    }

    /**
     * @return array{total: int, per_petugas: array<int, array<string, mixed>>}
     */
    private function undangan(Rapat $rapat): array
    {
        $perPetugas = LogUndangan::query()
            ->join('users', 'users.id', '=', 'log_undangan.petugas_user_id')
            ->where('log_undangan.rapat_id', $rapat->id)
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw("users.name as petugas, COUNT(*) as total,
                SUM(log_undangan.metode = 'wa') as wa,
                SUM(log_undangan.metode = 'cetak') as cetak")
            ->get()
            ->map(fn ($baris) => [
                'petugas' => (string) $baris->petugas,
                'wa' => (int) $baris->wa,
                'cetak' => (int) $baris->cetak,
                'total' => (int) $baris->total,
            ])
            ->all();

        return [
            'total' => array_sum(array_column($perPetugas, 'total')),
            'per_petugas' => $perPetugas,
        ];
    }

    /**
     * @return array{total: int, nominal: float, per_petugas: array<int, array<string, mixed>>}
     */
    private function presensi(Rapat $rapat): array
    {
        $perPetugas = Presensi::query()
            ->join('peserta', 'peserta.id', '=', 'presensi.peserta_id')
            ->leftJoin('users', 'users.id', '=', 'presensi.dicatat_oleh')
            ->where('peserta.rapat_id', $rapat->id)
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw('users.name as petugas, COUNT(*) as total,
                COALESCE(SUM(presensi.nominal_transport_final), 0) as nominal')
            ->get()
            ->map(fn ($baris) => [
                'petugas' => (string) ($baris->petugas ?? '-'),
                'total' => (int) $baris->total,
                'nominal' => (float) $baris->nominal,
            ])
            ->all();

        return [
            'total' => array_sum(array_column($perPetugas, 'total')),
            'nominal' => array_sum(array_column($perPetugas, 'nominal')),
            'per_petugas' => $perPetugas,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kas(Rapat $rapat): array
    {
        return KasScanner::query()
            ->with('scanner')
            ->where('rapat_id', $rapat->id)
            ->get()
            ->map(fn (KasScanner $kas) => [
                'scanner' => $kas->scanner?->name ?? '-',
                'kas_awal' => (float) $kas->kas_awal,
                'terbayar' => $kas->terbayar(),
                'sisa' => $kas->sisa(),
                'sisa_fisik' => $kas->sisa_fisik === null ? null : (float) $kas->sisa_fisik,
                'selisih' => $kas->sisa_fisik === null ? null : round((float) $kas->sisa_fisik - $kas->sisa(), 2),
                'status' => $kas->labelStatus(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shu(): array
    {
        return Shu::query()
            ->groupBy('tahun_buku')
            ->orderByDesc('tahun_buku')
            ->selectRaw('tahun_buku, COUNT(*) as jumlah,
                COALESCE(SUM(balas_jasa_saham), 0) as saham,
                COALESCE(SUM(balas_jasa_pinjaman), 0) as pinjaman')
            ->get()
            ->map(fn ($baris) => [
                'tahun_buku' => (int) $baris->tahun_buku,
                'jumlah' => (int) $baris->jumlah,
                'saham' => (float) $baris->saham,
                'pinjaman' => (float) $baris->pinjaman,
                'total' => (float) $baris->saham + (float) $baris->pinjaman,
            ])
            ->all();
    }

    /**
     * @return array{menang: int, gugur: int, hadiah: array<int, array<string, mixed>>, pemenang: array<int, array<string, mixed>>}
     */
    private function doorprize(Rapat $rapat): array
    {
        $semua = DoorprizePemenang::query()
            ->with('peserta.anggota')
            ->where('rapat_id', $rapat->id)
            ->orderBy('id')
            ->get();

        $pemenang = $semua->where('hasil', DoorprizePemenang::MENANG)
            ->map(fn (DoorprizePemenang $baris) => [
                'hadiah' => $baris->nama_hadiah_snapshot ?? '-',
                'nama' => $baris->peserta?->anggota?->nama ?? '-',
                'nba' => $baris->peserta?->anggota?->nbaTampil() ?? '-',
            ])
            ->values()
            ->all();

        $hadiah = DoorprizeHadiah::query()
            ->where('rapat_id', $rapat->id)
            ->withCount(['hasil as terisi' => fn ($query) => $query->where('hasil', DoorprizePemenang::MENANG)])
            ->orderBy('id')
            ->get()
            ->map(fn (DoorprizeHadiah $baris) => [
                'nama' => $baris->nama,
                'jumlah' => $baris->jumlah,
                'terisi' => $baris->terisi,
                'sisa' => max(0, $baris->jumlah - $baris->terisi),
            ])
            ->all();

        return [
            'menang' => count($pemenang),
            'gugur' => $semua->where('hasil', DoorprizePemenang::GUGUR)->count(),
            'hadiah' => $hadiah,
            'pemenang' => $pemenang,
        ];
    }
}
