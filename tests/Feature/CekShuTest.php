<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\LogAudit;
use App\Models\LogCekShu;
use App\Models\PengaturanIntegrasi;
use App\Models\Shu;
use App\Models\ShuPublik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * BR-011 / BR-SHU-LOG-01 / D-029: cek SHU publik memakai NBA + CAPTCHA,
 * dicatat di `log_cek_shu` tanpa nominal, dan dibersihkan setelah 14 hari.
 */
class CekShuTest extends TestCase
{
    use RefreshDatabase;

    private function anggota(string $nba = '020006000010339', string $nama = 'Anggota SHU'): Anggota
    {
        return Anggota::create([
            'no_anggota' => $nba,
            'nama' => $nama,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
            'simpanan_wajib' => 0,
        ]);
    }

    private function shu(Anggota $anggota, int $tahun = 2026, float $saham = 150000, float $pinjaman = 75000): Shu
    {
        return Shu::create([
            'anggota_id' => $anggota->id,
            'tahun_buku' => $tahun,
            'balas_jasa_saham' => $saham,
            'balas_jasa_pinjaman' => $pinjaman,
        ]);
    }

    private function buka(int $tahun = 2026): ShuPublik
    {
        return ShuPublik::create([
            'tahun_buku' => $tahun,
            'aktif' => true,
            'dibuka_at' => now(),
        ]);
    }

    private function captcha(bool $aktif = true): PengaturanIntegrasi
    {
        return PengaturanIntegrasi::create([
            'layanan' => PengaturanIntegrasi::CAPTCHA,
            'aktif' => $aktif,
            'provider' => 'turnstile',
            'site_key' => 'site-key-uji',
            'secret' => 'secret-uji',
        ]);
    }

    private function captchaLolos(bool $lolos = true): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => $lolos]),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('cek-shu:127.0.0.1');
    }

    public function test_halaman_publik_tanpa_tahun_dibuka_tidak_menampilkan_form(): void
    {
        $this->get(route('cek-shu'))
            ->assertOk()
            ->assertSee('Cek SHU publik belum dibuka Admin')
            ->assertDontSee('name="nba"', false);
    }

    public function test_halaman_publik_menampilkan_widget_turnstile_saat_siap(): void
    {
        $this->buka();
        $this->captcha();

        $this->get(route('cek-shu'))
            ->assertOk()
            ->assertSee('site-key-uji')
            ->assertSee('cf-turnstile', false)
            ->assertSee('challenges.cloudflare.com', false);
    }

    public function test_captcha_belum_siap_menolak_pencarian_tertutup(): void
    {
        $this->anggota();
        $this->buka();
        $this->captcha(aktif: false);

        $this->post(route('cek-shu.cari'), ['tahun_buku' => 2026, 'nba' => '020006000010339'])
            ->assertSessionHasErrors('captcha');

        $this->assertDatabaseHas('log_cek_shu', ['hasil' => LogCekShu::DITOLAK_CAPTCHA]);
    }

    public function test_captcha_ditolak_vendor_tidak_membaca_shu(): void
    {
        $this->shu($this->anggota());
        $this->buka();
        $this->captcha();
        $this->captchaLolos(false);

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006000010339',
            'cf-turnstile-response' => 'token-palsu',
        ])->assertSessionHasErrors('captcha');

        $this->assertDatabaseHas('log_cek_shu', ['hasil' => LogCekShu::DITOLAK_CAPTCHA, 'nba_dicari' => '020006000010339']);
        $this->assertNull(session('hasil'));
    }

    public function test_nba_ditemukan_menampilkan_nominal_dan_mencatat_log(): void
    {
        $this->shu($this->anggota());
        $this->buka();
        $this->captcha();
        $this->captchaLolos();

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006.000.010.339',
            'cf-turnstile-response' => 'token-uji',
        ])->assertRedirect(route('cek-shu'));

        $hasil = session('hasil');

        $this->assertSame(225000.0, $hasil['total']);
        $this->assertSame('020006.000.010.339', $hasil['nba']);

        $log = LogCekShu::query()->sole();

        $this->assertSame(LogCekShu::DITEMUKAN, $log->hasil);
        $this->assertSame('020006000010339', $log->nba_dicari);
        $this->assertSame(2026, $log->tahun_buku);
        // Log tidak boleh menyimpan nominal SHU.
        $this->assertArrayNotHasKey('nominal', $log->getAttributes());
        $this->assertStringNotContainsString('225000', json_encode($log->getAttributes()));
    }

    public function test_nba_tidak_ada_dicatat_tidak_ditemukan(): void
    {
        $this->buka();
        $this->captcha();
        $this->captchaLolos();

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006000099999',
            'cf-turnstile-response' => 'token-uji',
        ])->assertSessionHasErrors('nba');

        $this->assertDatabaseHas('log_cek_shu', ['hasil' => LogCekShu::TIDAK_DITEMUKAN]);
    }

    public function test_tahun_buku_tertutup_atau_nba_tidak_valid_ditolak(): void
    {
        $this->shu($this->anggota());
        $this->captcha();
        $this->captchaLolos();

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006000010339',
            'cf-turnstile-response' => 'token-uji',
        ])->assertSessionHasErrors('nba');

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '123',
            'cf-turnstile-response' => 'token-uji',
        ])->assertSessionHasErrors('nba');

        $this->assertSame(2, LogCekShu::query()->where('hasil', LogCekShu::TIDAK_VALID)->count());
        $this->assertSame(0, LogCekShu::query()->where('hasil', LogCekShu::DITEMUKAN)->count());
    }

    public function test_rate_limit_menolak_sebelum_query_dan_dicatat(): void
    {
        $this->shu($this->anggota());
        $this->buka();
        $this->captcha();
        $this->captchaLolos();

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('cek-shu.cari'), [
                'tahun_buku' => 2026,
                'nba' => '020006000010339',
                'cf-turnstile-response' => 'token-uji',
            ]);
        }

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006000010339',
            'cf-turnstile-response' => 'token-uji',
        ])->assertSessionHasErrors('nba');

        $this->assertDatabaseHas('log_cek_shu', ['hasil' => LogCekShu::DIBATASI]);
        $this->assertSame(10, LogCekShu::query()->where('hasil', LogCekShu::DITEMUKAN)->count());
    }

    public function test_purge_menghapus_hanya_log_lebih_tua_dari_14_hari(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');

        $lama = LogCekShu::create(['hasil' => LogCekShu::DITEMUKAN, 'created_at' => now()->subDays(15)]);
        $batas = LogCekShu::create(['hasil' => LogCekShu::DITEMUKAN, 'created_at' => now()->subDays(14)]);
        $baru = LogCekShu::create(['hasil' => LogCekShu::DITEMUKAN, 'created_at' => now()->subDays(13)]);

        $anggota = $this->anggota();
        $this->shu($anggota);
        $audit = LogAudit::catat('impor', 'shu', 1, null, ['baris' => 1]);

        $this->artisan('cek-shu:purge')->assertSuccessful();

        $this->assertDatabaseMissing('log_cek_shu', ['id' => $lama->id]);
        $this->assertDatabaseHas('log_cek_shu', ['id' => $batas->id]);
        $this->assertDatabaseHas('log_cek_shu', ['id' => $baru->id]);

        // Data SHU dan audit Admin tidak ikut terhapus.
        $this->assertSame(1, Shu::query()->count());
        $this->assertDatabaseHas('log_audit', ['id' => $audit->id]);

        Carbon::setTestNow();
    }

    public function test_purge_idempoten_dan_bertahap(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');

        foreach (range(1, 5) as $i) {
            LogCekShu::create(['hasil' => LogCekShu::DITEMUKAN, 'created_at' => now()->subDays(20)]);
        }

        $this->artisan('cek-shu:purge', ['--chunk' => 2])->assertSuccessful();
        $this->assertSame(0, LogCekShu::query()->count());

        $this->artisan('cek-shu:purge')->assertSuccessful();
        $this->assertSame(0, LogCekShu::query()->count());

        Carbon::setTestNow();
    }

    public function test_jadwal_purge_harian_terdaftar(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('cek-shu:purge')
            ->assertSuccessful();
    }

    public function test_riwayat_pencarian_hanya_dibaca_admin(): void
    {
        $this->anggota();
        $this->buka();
        $this->captcha();
        $this->captchaLolos();

        $this->post(route('cek-shu.cari'), [
            'tahun_buku' => 2026,
            'nba' => '020006000010339',
            'cf-turnstile-response' => 'token-uji',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Riwayat pencarian SHU')
            ->assertSee('020006.000.010.339')
            ->assertSee('ditemukan');

        foreach (['helpdesk', 'keuangan', 'scanner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('audit.index'))
                ->assertForbidden();
        }
    }
}
