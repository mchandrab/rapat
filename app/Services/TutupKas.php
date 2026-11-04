<?php

namespace App\Services;

use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * BR-009 / SOP: tutup kas per Scanner setelah uang fisik dihitung bersama.
 * Selisih bukan nol wajib beralasan (keputusan 3 November 2026: cukup alasan + audit).
 */
final class TutupKas
{
    public function tutup(KasScanner $kas, User $petugas, float $sisaFisik, ?string $catatan): KasScanner
    {
        if ($kas->status === KasScanner::DITUTUP) {
            throw new LogicException('Kas ini sudah ditutup.');
        }

        $selisih = $this->selisih($kas, $sisaFisik);

        if (abs($selisih) >= 0.005 && trim((string) $catatan) === '') {
            throw new LogicException(
                'Selisih '.number_format($selisih, 0, ',', '.').' wajib diberi alasan sebelum kas ditutup.'
            );
        }

        return DB::transaction(function () use ($kas, $petugas, $sisaFisik, $catatan, $selisih): KasScanner {
            $sebelum = $kas->only(['status', 'sisa_fisik', 'catatan_selisih']);

            $kas->status = KasScanner::DITUTUP;
            $kas->sisa_fisik = $sisaFisik;
            $kas->catatan_selisih = $catatan;
            $kas->ditutup_oleh = $petugas->id;
            $kas->ditutup_pada = now();
            $kas->save();

            $sesudah = $kas->only(['status', 'sisa_fisik', 'catatan_selisih', 'ditutup_oleh', 'ditutup_pada']);
            $sesudah['selisih'] = $selisih;

            LogAudit::catat('tutup_kas', 'kas_scanner', $kas->id, $sebelum, $sesudah, $catatan);

            return $kas;
        });
    }

    /** Sisa fisik − sisa seharusnya (kas awal − pembayaran sukses). */
    public function selisih(KasScanner $kas, float $sisaFisik): float
    {
        return round($sisaFisik - $kas->sisa(), 2);
    }
}
