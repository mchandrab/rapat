<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * BR-003/D-008/D-043: minimum simpanan wajib proporsional sampai 31 Desember Tahun Acuan.
 * Bulan dihitung inklusif dan dibatasi 12, dengan batas atas minimum tahunan rapat.
 * D-033: tgl_ja kosong ditolak di `PenilaianKelayakan`; masuk setelah Tahun Acuan = 0 bulan.
 */
final class Kelayakan
{
    public static function minimumProporsional(?CarbonInterface $tglJa, int $tahunAcuan, float $tarifBulanan, float $minimumTahunan): ?float
    {
        if ($tglJa === null) {
            return null;
        }

        $bulan = match (true) {
            $tglJa->year > $tahunAcuan => 0,
            $tglJa->year === $tahunAcuan => 13 - $tglJa->month,
            default => 12,
        };

        return min($minimumTahunan, $bulan * $tarifBulanan);
    }
}
