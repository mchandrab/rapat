<?php

namespace App\Services;

use App\Models\Rapat;
use App\Models\RapatTransportTier;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * BR-008 / D-012: nominal akhir = max(0, dasar − potongan tier).
 * Acuan keterlambatan adalah jam mulai acara, bukan jam presensi.
 */
final class PenghitungTransport
{
    /**
     * @return array{menit_kena: int, potongan: float, nominal: float, tier: RapatTransportTier|null}
     */
    public static function hitung(Rapat $rapat, CarbonInterface $datang): array
    {
        $mulai = Carbon::parse($rapat->tgl_rapat->toDateString().' '.$rapat->jam_mulai_rat);
        $terlambat = max(0, (int) floor($mulai->diffInMinutes($datang, false)));
        $kena = max(0, $terlambat - (int) $rapat->toleransi_menit);

        $tier = null;

        if ($kena >= 1) {
            $tier = $rapat->tier->first(fn (RapatTransportTier $t) => $kena >= $t->menit_mulai && $kena <= $t->menit_akhir);

            // BR-008: kedatangan setelah tier terakhir memakai potongan tier terakhir, bukan potongan nol.
            if ($tier === null) {
                $terakhir = $rapat->tier->sortBy('menit_akhir')->last();
                $tier = $terakhir !== null && $kena > $terakhir->menit_akhir ? $terakhir : null;
            }
        }

        $potongan = $tier === null ? 0.0 : (float) $tier->potongan;

        return [
            'menit_kena' => $kena,
            'potongan' => $potongan,
            'nominal' => max(0.0, (float) $rapat->transport_dasar - $potongan),
            'tier' => $tier,
        ];
    }
}
