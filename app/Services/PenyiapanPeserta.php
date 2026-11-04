<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;

/**
 * BR-004: menyiapkan baris peserta pending untuk seluruh anggota pada satu rapat.
 * Idempoten: baris yang sudah ada tidak ditimpa agar keputusan verifikasi tidak hilang.
 */
final class PenyiapanPeserta
{
    /**
     * @return array{dibuat: int, dilewati: int}
     */
    public function siapkan(Rapat $rapat): array
    {
        $dibuat = 0;
        $dilewati = 0;

        foreach (Anggota::query()->orderBy('id')->cursor() as $anggota) {
            $sudah = Peserta::query()
                ->where('rapat_id', $rapat->id)
                ->where('anggota_id', $anggota->id)
                ->exists();

            if ($sudah) {
                $dilewati++;

                continue;
            }

            $nilai = PenilaianKelayakan::nilai($anggota, $rapat);

            Peserta::create([
                'rapat_id' => $rapat->id,
                'anggota_id' => $anggota->id,
                'hasil_kelayakan' => $nilai['hasil'],
                'status_verifikasi' => Peserta::PENDING,
                'snapshot_simpanan_wajib' => $anggota->simpanan_wajib,
                'snapshot_tgl_ja' => $anggota->tgl_ja,
                'snapshot_minimum_proporsional' => $nilai['minimum'],
                'snapshot_kolektibilitas' => $anggota->kolektibilitas,
                'snapshot_pinjaman_lancar' => $anggota->pinjaman_lancar,
                'snapshot_konfigurasi' => [
                    'tahun_acuan' => $rapat->tahun_acuan,
                    'mode_kelayakan' => $rapat->mode_kelayakan,
                    'batas_kolektibilitas' => $rapat->batas_kolektibilitas,
                    'wajib_pinjaman_lancar' => $rapat->wajib_pinjaman_lancar,
                    'minimal_simpanan_tahunan' => (float) $rapat->minimal_simpanan_tahunan,
                    'simpanan_per_bulan' => (float) $rapat->simpanan_per_bulan,
                    'gunakan_proporsional' => $rapat->gunakan_proporsional,
                ],
            ]);

            $dibuat++;
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati];
    }
}
