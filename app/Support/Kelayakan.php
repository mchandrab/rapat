<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * BR-003/D-051: minimum simpanan wajib proporsional sampai tanggal acuan saldo.
 * Bulan dihitung inklusif dan dibatasi 12, dengan batas atas minimum tahunan rapat.
 * D-033/D-051: tgl_ja kosong atau setelah tanggal acuan ditolak di `PenilaianKelayakan`.
 */
final class Kelayakan
{
    public static function minimumProporsional(?CarbonInterface $tglJa, CarbonInterface $tanggalAcuan, float $tarifBulanan, float $minimumTahunan): ?float
    {
        if ($tglJa === null) {
            return null;
        }

        $bulan = match (true) {
            $tglJa->greaterThan($tanggalAcuan) => 0,
            $tglJa->year === $tanggalAcuan->year => $tanggalAcuan->month - $tglJa->month + 1,
            default => $tanggalAcuan->month,
        };

        return min($minimumTahunan, min(12, $bulan) * $tarifBulanan);
    }
}
