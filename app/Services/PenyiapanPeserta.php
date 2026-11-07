<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;

/**
 * BR-004: menyiapkan baris peserta pending untuk seluruh anggota pada satu rapat.
 * Peserta Pending dihitung ulang secara eksplisit; keputusan Verified/Rejected tidak ditimpa.
 */
final class PenyiapanPeserta
{
    /**
     * @return array{dibuat: int, diperbarui: int, dilewati: int}
     */
    public function siapkan(Rapat $rapat): array
    {
        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;

        foreach (Anggota::query()->orderBy('id')->cursor() as $anggota) {
            $peserta = Peserta::query()
                ->where('rapat_id', $rapat->id)
                ->where('anggota_id', $anggota->id)
                ->first();

            if ($peserta !== null && $peserta->status_verifikasi !== Peserta::PENDING) {
                $dilewati++;

                continue;
            }

            $nilai = PenilaianKelayakan::nilai($anggota, $rapat);
            $snapshot = [
                'hasil_kelayakan' => $nilai['hasil'],
                'snapshot_simpanan_wajib' => $anggota->simpanan_wajib,
                'snapshot_tgl_ja' => $anggota->tgl_ja,
                'snapshot_minimum_proporsional' => $nilai['minimum'],
                'snapshot_kolektibilitas' => $anggota->kolektibilitas,
                'snapshot_pinjaman_lancar' => $anggota->pinjaman_lancar,
                'snapshot_konfigurasi' => [
                    'tanggal_acuan_saldo' => $rapat->tanggal_acuan_saldo->toDateString(),
                    'mode_kelayakan' => $rapat->mode_kelayakan,
                    'batas_kolektibilitas' => $rapat->batas_kolektibilitas,
                    'wajib_pinjaman_lancar' => $rapat->wajib_pinjaman_lancar,
                    'minimal_simpanan_tahunan' => (float) $rapat->minimal_simpanan_tahunan,
                    'simpanan_per_bulan' => (float) $rapat->simpanan_per_bulan,
                    'gunakan_proporsional' => $rapat->gunakan_proporsional,
                ],
            ];

            if ($peserta !== null) {
                $peserta->update($snapshot);
                $diperbarui++;

                continue;
            }

            Peserta::create(array_merge($snapshot, [
                'rapat_id' => $rapat->id,
                'anggota_id' => $anggota->id,
                'status_verifikasi' => Peserta::PENDING,
            ]));

            $dibuat++;
        }

        return ['dibuat' => $dibuat, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }
}
