<?php

namespace App\Services;

use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * D-013/D-014: Keuangan menyerahkan kas awal ke akun Scanner, sekali per rapat.
 * Tidak ada top-up, tambah kas, atau transfer antar-Scanner.
 */
final class AlokasiKas
{
    public function serahkan(Rapat $rapat, User $scanner, User $petugas, float $kasAwal): KasScanner
    {
        if ($scanner->role !== 'scanner') {
            throw new InvalidArgumentException('Kas hanya dapat dialokasikan ke akun ber-role Scanner.');
        }

        if (in_array($rapat->status, [Rapat::SELESAI, Rapat::DIBATALKAN], true)) {
            throw new LogicException("Kas tidak dapat dialokasikan karena rapat sudah {$rapat->labelStatus()}.");
        }

        if ($this->sudahAda($rapat, $scanner)) {
            throw new LogicException('Kas awal Scanner ini pada rapat ini sudah dialokasikan. Top-up tidak diizinkan.');
        }

        return DB::transaction(function () use ($rapat, $scanner, $petugas, $kasAwal): KasScanner {
            $kas = KasScanner::create([
                'rapat_id' => $rapat->id,
                'scanner_user_id' => $scanner->id,
                'kas_awal' => $kasAwal,
                'diserahkan_oleh' => $petugas->id,
                'diserahkan_pada' => now(),
                // D-026: kas belum boleh dipakai memindai sebelum Scanner mengonfirmasi terima.
                'status' => KasScanner::MENUNGGU,
            ]);

            LogAudit::catat('alokasi', 'kas_scanner', $kas->id, null, $kas->only([
                'rapat_id', 'scanner_user_id', 'kas_awal', 'diserahkan_oleh', 'diserahkan_pada', 'status',
            ]));

            return $kas;
        });
    }

    /**
     * D-026: Scanner mengonfirmasi uang sudah diterima; hanya sekali per alokasi.
     */
    public function terima(KasScanner $kas, User $scanner): KasScanner
    {
        if ($kas->scanner_user_id !== $scanner->id) {
            throw new LogicException('Kas ini bukan kas Anda.');
        }

        if ($kas->status !== KasScanner::MENUNGGU) {
            throw new LogicException('Kas ini sudah dikonfirmasi atau sudah ditutup.');
        }

        return DB::transaction(function () use ($kas): KasScanner {
            $sebelum = $kas->only(['status', 'diterima_pada']);

            $kas->status = KasScanner::AKTIF;
            $kas->diterima_pada = now();
            $kas->save();

            LogAudit::catat('terima_kas', 'kas_scanner', $kas->id, $sebelum, $kas->only(['status', 'diterima_pada']));

            return $kas;
        });
    }

    public function sudahAda(Rapat $rapat, User $scanner): bool
    {
        return KasScanner::query()
            ->where('rapat_id', $rapat->id)
            ->where('scanner_user_id', $scanner->id)
            ->exists();
    }

    /**
     * Pembetulan nominal salah input. Bukan top-up: nominal diganti, bukan ditambah,
     * dan hanya selama kas belum dipakai membayar. Jejak lama tetap di log_audit.
     */
    public function koreksi(KasScanner $kas, float $kasAwalBaru, string $alasan): KasScanner
    {
        if (! in_array($kas->status, [KasScanner::AKTIF, KasScanner::MENUNGGU], true)) {
            throw new LogicException('Kas yang sudah ditutup tidak dapat dikoreksi.');
        }

        if ($this->adaPembayaran($kas)) {
            throw new LogicException('Kas yang sudah dipakai membayar tidak dapat dikoreksi.');
        }

        if ((float) $kas->kas_awal === $kasAwalBaru) {
            throw new LogicException('Nominal baru sama dengan nominal tercatat; tidak ada yang dikoreksi.');
        }

        return DB::transaction(function () use ($kas, $kasAwalBaru, $alasan): KasScanner {
            $sebelum = $kas->only(['kas_awal']);

            $kas->kas_awal = $kasAwalBaru;
            $kas->save();

            LogAudit::catat('koreksi', 'kas_scanner', $kas->id, $sebelum, ['kas_awal' => $kasAwalBaru], $alasan);

            return $kas;
        });
    }

    /**
     * Koreksi ditutup begitu ada uang transport yang benar-benar dibayarkan.
     */
    private function adaPembayaran(KasScanner $kas): bool
    {
        return $kas->pembayaran()->exists();
    }
}
