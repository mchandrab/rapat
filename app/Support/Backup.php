<?php

namespace App\Support;

/**
 * Fase 10: retensi berkas cadangan. Berkas di luar awalan `erat-`/`media-`
 * tidak pernah disentuh.
 */
final class Backup
{
    /** Hapus berkas cadangan lebih tua dari $hari hari; mengembalikan jumlah yang dihapus. */
    public static function prune(string $dir, int $hari): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $batas = now()->subDays(max(1, $hari))->getTimestamp();
        $terhapus = 0;

        foreach (scandir($dir) ?: [] as $nama) {
            $berkas = $dir.DIRECTORY_SEPARATOR.$nama;

            if (! is_file($berkas) || ! preg_match('/^(erat|media)-/', $nama)) {
                continue;
            }

            if (filemtime($berkas) < $batas && @unlink($berkas)) {
                $terhapus++;
            }
        }

        return $terhapus;
    }
}
