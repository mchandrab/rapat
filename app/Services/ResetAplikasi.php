<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\DoorprizePemenang;
use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\LogCekShu;
use App\Models\LogUndangan;
use App\Models\PengaturanIntegrasi;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\RapatTransportTier;
use App\Models\Shu;
use App\Models\ShuPublik;
use App\Models\TransportPembayaran;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reset total: menghapus seluruh data bisnis, akun, dan berkas bukti,
 * lalu membuat ulang akun Admin saja. Setara `migrate:fresh --seed`
 * tanpa menjatuhkan skema, sehingga aman dijalankan dari aplikasi.
 */
final class ResetAplikasi
{
    public function jalankan(): void
    {
        DB::transaction(function (): void {
            // Urut anak ke induk: pembayaran menahan kas lewat FK restrict.
            TransportPembayaran::query()->delete();
            Presensi::query()->delete();
            LogUndangan::query()->delete();
            KasScanner::query()->delete();
            DoorprizePemenang::query()->delete();
            Peserta::query()->delete();
            RapatTransportTier::query()->delete();
            Rapat::query()->delete();
            Shu::query()->delete();
            ShuPublik::query()->delete();
            LogCekShu::query()->delete();
            Anggota::query()->delete();
            LogAudit::query()->delete();
            PengaturanIntegrasi::query()->delete();
            User::query()->delete();

            foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $tabel) {
                if (Schema::hasTable($tabel)) {
                    DB::table($tabel)->delete();
                }
            }

            // Di dalam transaksi: bila seeding gagal, seluruh reset dibatalkan.
            Artisan::call('db:seed', ['--class' => UserSeeder::class, '--force' => true]);
        });

        Storage::disk('local')->deleteDirectory('presensi');
    }
}
