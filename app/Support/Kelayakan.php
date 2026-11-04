<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * BR-003/D-008: minimum simpanan wajib proporsional sampai 31 Desember tahun buku.
 * Bulan dihitung inklusif dan dibatasi 12, dengan batas atas minimum tahunan rapat.
 * D-033: tgl_ja kosong ditolak di `PenilaianKelayakan`; masuk setelah tahun buku = 0 bulan.
 */
final class Kelayakan
{
    public static function minimumProporsional(?CarbonInterface $tglJa, int $tahunBuku, float $tarifBulanan, float $minimumTahunan): ?float
    {
        if ($tglJa === null) {
            return null;
        }

        $bulan = match (true) {
            $tglJa->year > $tahunBuku => 0,
            $tglJa->year === $tahunBuku => 13 - $tglJa->month,
            default => 12,
        };

        return min($minimumTahunan, $bulan * $tarifBulanan);
    }
}
