<?php

namespace App\Support;

use App\Models\Rapat;

/**
 * Pembantu tampilan. Data contoh sudah dihapus: aplikasi mulai dari database
 * kosong sehingga setiap angka yang tampil berasal dari tabel nyata.
 */
final class Demo
{
    public static function rupiah(int|float $nominal): string
    {
        return 'Rp'.number_format((float) $nominal, 0, ',', '.');
    }

    /**
     * Parameter rapat dibaca dari database agar seluruh halaman memakai angka yang sama.
     */
    public static function rapat(): array
    {
        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            $bawaan = Rapat::bawaan();

            return [
                'judul' => $bawaan['judul'],
                'tahun_buku' => $bawaan['tahun_buku'],
                'tanggal_acuan' => '31/12/'.$bawaan['tahun_buku'],
                'tgl_rapat' => date('d/m/Y', strtotime($bawaan['tgl_rapat'])),
                'jam_presensi' => $bawaan['jam_mulai_presensi'],
                'jam_rat' => $bawaan['jam_mulai_rat'],
                'lokasi' => $bawaan['lokasi'],
                'status' => 'Draf',
                'mode_presensi' => 'Mode Presensi 1x',
                'mode_kelayakan' => 'Mode A — Simpanan Wajib & Kolektibilitas',
                'minimal_simpanan_tahunan' => $bawaan['minimal_simpanan_tahunan'],
                'simpanan_per_bulan' => $bawaan['simpanan_per_bulan'],
                'transport_dasar' => $bawaan['transport_dasar'],
                'toleransi_menit' => $bawaan['toleransi_menit'],
                'presensi_dibuka' => false,
            ];
        }

        return [
            'judul' => $rapat->judul,
            'tahun_buku' => $rapat->tahun_buku,
            'tanggal_acuan' => $rapat->tanggal_acuan_saldo->format('d/m/Y'),
            'tgl_rapat' => $rapat->tgl_rapat->format('d/m/Y'),
            'jam_presensi' => $rapat->jam_mulai_presensi,
            'jam_rat' => $rapat->jam_mulai_rat,
            'lokasi' => $rapat->lokasi,
            'status' => $rapat->labelStatus(),
            'mode_presensi' => $rapat->labelModePresensi(),
            'mode_kelayakan' => $rapat->labelModeKelayakan(),
            'minimal_simpanan_tahunan' => (float) $rapat->minimal_simpanan_tahunan,
            'simpanan_per_bulan' => (float) $rapat->simpanan_per_bulan,
            'transport_dasar' => (float) $rapat->transport_dasar,
            'toleransi_menit' => $rapat->toleransi_menit,
            'presensi_dibuka' => $rapat->presensi_dibuka_at !== null,
        ];
    }
}
