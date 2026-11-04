<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Fase 10: cadangan MySQL + media privat (foto/TTE) ke satu folder.
 * Command ini hanya membaca database; pemulihan diuji manual menurut
 * DEPLOYMENT_RUNBOOK agar tidak ada penimpaan data tanpa disengaja.
 */
class BackupDatabase extends Command
{
    protected $signature = 'erat:backup
        {--dir= : Folder tujuan (bawaan storage/app/backup)}
        {--keep=7 : Hapus cadangan lebih tua dari sekian hari}
        {--binary= : Path mysqldump bila tidak ada di PATH}';

    protected $description = 'Buat cadangan .sql database MySQL dan arsip media privat';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Cadangan hanya tersedia untuk koneksi MySQL.');

            return self::FAILURE;
        }

        $koneksi = (array) config('database.connections.mysql');
        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/backup')), '\\/');
        $binary = (string) ($this->option('binary') ?: config('database.dump_binary', 'mysqldump'));
        $cap = now()->format('Ymd-His');

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Folder cadangan tidak dapat dibuat: {$dir}");

            return self::FAILURE;
        }

        $sql = $dir.DIRECTORY_SEPARATOR."erat-{$cap}.sql";
        $kredensial = (string) tempnam(sys_get_temp_dir(), 'eratdb');

        // Kredensial lewat berkas sementara agar password tidak tampil di daftar proses.
        file_put_contents($kredensial, implode("\n", [
            '[client]',
            'user='.$koneksi['username'],
            'password='.$koneksi['password'],
            'host='.$koneksi['host'],
            'port='.$koneksi['port'],
        ])."\n");
        @chmod($kredensial, 0600);

        try {
            $proses = new Process([
                $binary,
                '--defaults-extra-file='.$kredensial,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--events',
                (string) $koneksi['database'],
            ]);
            $proses->setTimeout(3600);
            $proses->run();

            if (! $proses->isSuccessful()) {
                @unlink($sql);
                $this->error('mysqldump gagal: '.trim($proses->getErrorOutput()));

                return self::FAILURE;
            }

            file_put_contents($sql, $proses->getOutput());
        } catch (Throwable $e) {
            @unlink($sql);
            $this->error('Cadangan database gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($kredensial);
        }

        $media = $this->arsipkanMedia($dir, $cap);
        $terhapus = Backup::prune($dir, (int) $this->option('keep'));

        $this->info("Cadangan database: {$sql} (".number_format(filesize($sql) / 1024, 0, ',', '.').' KB)');

        if ($media !== null) {
            $this->info("Arsip media: {$media}");
        }

        if ($terhapus > 0) {
            $this->line("Cadangan lebih tua dari {$this->option('keep')} hari dihapus: {$terhapus} berkas.");
        }

        return self::SUCCESS;
    }

    private function arsipkanMedia(string $dir, string $cap): ?string
    {
        $sumber = storage_path('app/private');

        if (! is_dir($sumber)) {
            return null;
        }

        if (! class_exists(ZipArchive::class)) {
            $this->warn('Ekstensi zip tidak aktif; media privat tidak diarsipkan.');

            return null;
        }

        $tujuan = $dir.DIRECTORY_SEPARATOR."media-{$cap}.zip";
        $zip = new ZipArchive;

        if ($zip->open($tujuan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->warn("Arsip media gagal dibuat: {$tujuan}");

            return null;
        }

        $daftar = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sumber, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($daftar as $berkas) {
            if ($berkas->isFile()) {
                $relatif = str_replace('\\', '/', substr($berkas->getPathname(), strlen($sumber) + 1));
                $zip->addFile($berkas->getPathname(), 'private/'.$relatif);
            }
        }

        $zip->close();

        return $tujuan;
    }
}
