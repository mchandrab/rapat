<?php

namespace App\Console\Commands;

use App\Models\LogCekShu;
use Illuminate\Console\Command;

/**
 * BR-SHU-LOG-01: hapus bertahap log pencarian SHU lebih tua dari 14 hari.
 * Tidak menyentuh tabel `shu` maupun `log_audit`.
 */
class PurgeLogCekShu extends Command
{
    protected $signature = 'cek-shu:purge {--days=14 : Umur maksimum log dalam hari} {--chunk=1000 : Jumlah baris per batch}';

    protected $description = 'Hapus log pencarian SHU publik yang lebih tua dari batas retensi';

    public function handle(): int
    {
        $batas = now()->subDays((int) $this->option('days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;

        do {
            $ids = LogCekShu::query()
                ->where('created_at', '<', $batas)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += LogCekShu::query()->whereIn('id', $ids)->delete();
        } while (true);

        $this->info("Log cek SHU dihapus: {$total} baris (batas {$batas->format('d/m/Y H:i')}).");

        return self::SUCCESS;
    }
}
