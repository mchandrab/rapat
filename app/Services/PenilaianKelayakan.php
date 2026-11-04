<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Support\Kelayakan;

/**
 * BR-003: Mode A = simpanan wajib cukup DAN kolektibilitas <= batas rapat.
 * Mode B = simpanan wajib cukup DAN pinjaman lancar. Status keanggotaan bukan syarat.
 */
final class PenilaianKelayakan
{
    /**
     * @return array{hasil: string, minimum: float, alasan: list<string>}
     */
    public static function nilai(Anggota $anggota, Rapat $rapat): array
    {
        $minimum = self::minimum($anggota, $rapat);
        $alasan = [];

        // O-008/D-033: tanpa tgl_ja, keringanan proporsional tidak dapat dihitung.
        if ($rapat->gunakan_proporsional && $anggota->tgl_ja === null) {
            $alasan[] = 'Tanggal jadi anggota belum lengkap';
        } elseif ((float) $anggota->simpanan_wajib < $minimum) {
            $alasan[] = 'Simpanan wajib di bawah minimum '.number_format($minimum, 0, ',', '.');
        }

        if ($rapat->mode_kelayakan === 'B') {
            if (! $anggota->pinjaman_lancar) {
                $alasan[] = 'Pinjaman tidak lancar (Mode B)';
            }
        } elseif ((int) $anggota->kolektibilitas > (int) $rapat->batas_kolektibilitas) {
            $alasan[] = 'Kolektibilitas melebihi batas rapat';
        }

        if ($rapat->wajib_pinjaman_lancar && ! $anggota->pinjaman_lancar) {
            $alasan[] = 'Pinjaman tidak lancar (kriteria tambahan rapat)';
        }

        return [
            'hasil' => $alasan === [] ? Peserta::LAYAK : Peserta::TIDAK_LAYAK,
            'minimum' => $minimum,
            'alasan' => $alasan,
        ];
    }

    /**
     * D-008: minimum proporsional dihitung dari tgl_ja sampai 31 Desember tahun buku.
     * O-008: bila tgl_ja tidak lengkap, dipakai minimum tahunan penuh (dapat diubah di sini saja).
     */
    public static function minimum(Anggota $anggota, Rapat $rapat): float
    {
        $penuh = (float) $rapat->minimal_simpanan_tahunan;

        if (! $rapat->gunakan_proporsional) {
            return $penuh;
        }

        $proporsional = Kelayakan::minimumProporsional(
            $anggota->tgl_ja,
            (int) $rapat->tahun_buku,
            (float) $rapat->simpanan_per_bulan,
            $penuh,
        );

        return (float) ($proporsional ?? $penuh);
    }
}
