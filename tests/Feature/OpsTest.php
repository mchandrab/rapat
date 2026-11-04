<?php

namespace Tests\Feature;

use App\Models\Rapat;
use App\Support\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 10: kesiapan operasional. Cadangan menolak koneksi non-MySQL,
 * retensi hanya menghapus berkas cadangan, dan benchmark membaca data nyata.
 */
class OpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadangan_menolak_koneksi_bukan_mysql(): void
    {
        // Test suite memakai SQLite; command harus berhenti sebelum menyentuh berkas.
        $this->artisan('erat:backup')
            ->expectsOutputToContain('hanya tersedia untuk koneksi MySQL')
            ->assertFailed();
    }

    public function test_retensi_hanya_menghapus_berkas_cadangan_lama(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'erat-backup-uji-'.uniqid();
        mkdir($dir);

        file_put_contents($dir.DIRECTORY_SEPARATOR.'erat-lama.sql', 'x');
        touch($dir.DIRECTORY_SEPARATOR.'erat-lama.sql', now()->subDays(10)->getTimestamp());
        file_put_contents($dir.DIRECTORY_SEPARATOR.'media-lama.zip', 'x');
        touch($dir.DIRECTORY_SEPARATOR.'media-lama.zip', now()->subDays(10)->getTimestamp());
        file_put_contents($dir.DIRECTORY_SEPARATOR.'erat-baru.sql', 'x');
        file_put_contents($dir.DIRECTORY_SEPARATOR.'catatan.txt', 'x');
        touch($dir.DIRECTORY_SEPARATOR.'catatan.txt', now()->subDays(10)->getTimestamp());

        $this->assertSame(2, Backup::prune($dir, 7));

        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'erat-lama.sql');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'media-lama.zip');
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'erat-baru.sql');
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'catatan.txt');

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') as $berkas) {
            unlink($berkas);
        }

        rmdir($dir);
    }

    public function test_benchmark_mengukur_rapat_terakhir(): void
    {
        Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
        ]));

        $this->artisan('erat:benchmark')
            ->expectsOutputToContain('Kuorum')
            ->expectsOutputToContain('Laporan semua bagian')
            ->assertSuccessful();
    }

    public function test_benchmark_tanpa_rapat_ditolak(): void
    {
        $this->artisan('erat:benchmark')
            ->expectsOutputToContain('Belum ada rapat')
            ->assertFailed();
    }

    public function test_cadangan_harian_terjadwal(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('erat:backup')
            ->assertSuccessful();
    }
}
