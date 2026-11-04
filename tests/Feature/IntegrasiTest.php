<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\LogUndangan;
use App\Models\PengaturanIntegrasi;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Models\User;
use App\Services\PenyiapanPeserta;
use App\Services\UndanganPeserta;
use App\Services\VerifikasiPeserta;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fase Integrasi v2.2 (D-019 / BR-INT-01): secret terenkripsi, tidak pernah
 * ditampilkan kembali, dan menyimpan form tidak memanggil vendor.
 */
class IntegrasiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function captchaAktif(string $secret = 'rahasia-captcha'): PengaturanIntegrasi
    {
        $this->actingAs($this->admin())->put(route('integrasi.captcha.simpan'), [
            'aktif' => '1',
            'provider' => 'turnstile',
            'site_key' => '0x4AAAA-situs',
            'secret' => $secret,
        ])->assertRedirect();

        return PengaturanIntegrasi::query()->where('layanan', 'captcha')->firstOrFail();
    }

    private function fonnteAktif(string $token = 'token-fonnte'): PengaturanIntegrasi
    {
        $this->actingAs($this->admin())->put(route('integrasi.fonnte.simpan'), [
            'aktif' => '1',
            'label' => 'Koperasi Kredit Rukun',
            'token' => $token,
        ])->assertRedirect();

        return PengaturanIntegrasi::query()->where('layanan', 'fonnte')->firstOrFail();
    }

    public function test_hanya_admin_dapat_membuka_halaman_integrasi(): void
    {
        $this->get(route('integrasi.index'))->assertRedirect(route('login'));

        $this->actingAs($this->admin())
            ->get(route('integrasi.index'))
            ->assertOk()
            ->assertSee('Cloudflare Turnstile')
            ->assertSee('Fonnte WhatsApp');

        foreach (['helpdesk', 'scanner', 'keuangan'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('integrasi.index'))
                ->assertForbidden();
        }
    }

    public function test_secret_captcha_tersimpan_terenkripsi_dan_tidak_tampil_lagi(): void
    {
        $row = $this->captchaAktif('rahasia-captcha');

        $this->assertNotSame('rahasia-captcha', $row->getRawOriginal('secret'));
        $this->assertSame('rahasia-captcha', $row->secret);
        $this->assertSame(1, (int) $row->secret_versi);

        $this->actingAs($this->admin())
            ->get(route('integrasi.index'))
            ->assertOk()
            ->assertDontSee('rahasia-captcha')
            ->assertSee('Tersimpan — kosongkan untuk mempertahankan');
    }

    public function test_secret_kosong_mempertahankan_secret_lama(): void
    {
        $this->captchaAktif('rahasia-lama');

        $this->actingAs($this->admin())->put(route('integrasi.captcha.simpan'), [
            'aktif' => '1',
            'provider' => 'turnstile',
            'site_key' => '0x4AAAA-baru',
            'secret' => '',
        ])->assertRedirect();

        $row = PengaturanIntegrasi::query()->where('layanan', 'captcha')->firstOrFail();

        $this->assertSame('rahasia-lama', $row->secret);
        $this->assertSame('0x4AAAA-baru', $row->site_key);
        $this->assertSame(1, (int) $row->secret_versi);
    }

    public function test_nonaktifkan_menghapus_secret_dan_mematikan_integrasi(): void
    {
        $this->captchaAktif();
        $this->fonnteAktif();

        $this->actingAs($this->admin())->post(route('integrasi.captcha.nonaktif'))->assertRedirect();
        $this->actingAs($this->admin())->post(route('integrasi.fonnte.nonaktif'))->assertRedirect();

        foreach (['captcha', 'fonnte'] as $layanan) {
            $row = PengaturanIntegrasi::query()->where('layanan', $layanan)->firstOrFail();

            $this->assertFalse($row->aktif);
            $this->assertNull($row->getRawOriginal('secret'));
            $this->assertFalse($row->siap());
            $this->assertSame(2, (int) $row->secret_versi);
        }
    }

    public function test_uji_captcha_memanggil_vendor_dan_mencatat_hasil(): void
    {
        $this->captchaAktif();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('integrasi.captcha.uji'), ['token' => 'token-challenge'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'siteverify')
            && $request['secret'] === 'rahasia-captcha'
            && $request['response'] === 'token-challenge');

        $row = PengaturanIntegrasi::query()->where('layanan', 'captcha')->firstOrFail();
        $this->assertSame(PengaturanIntegrasi::DITERIMA_API, $row->status_uji);
        $this->assertNotNull($row->status_uji_pada);
    }

    public function test_uji_captcha_gagal_tertutup_saat_vendor_menolak(): void
    {
        $this->captchaAktif();

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('integrasi.captcha.uji'), ['token' => 'token-salah'])
            ->assertRedirect()
            ->assertSessionHasErrors('token');

        $this->assertSame(
            PengaturanIntegrasi::GAGAL,
            PengaturanIntegrasi::query()->where('layanan', 'captcha')->firstOrFail()->status_uji,
        );
    }

    public function test_uji_captcha_menolak_saat_secret_belum_ada(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->post(route('integrasi.captcha.uji'), ['token' => 'token-challenge'])
            ->assertSessionHasErrors('token');

        Http::assertNothingSent();
    }

    public function test_menyimpan_fonnte_tidak_mengirim_pesan(): void
    {
        Http::fake();

        $this->fonnteAktif();

        Http::assertNothingSent();

        $row = PengaturanIntegrasi::query()->where('layanan', 'fonnte')->firstOrFail();
        $this->assertNotSame('token-fonnte', $row->getRawOriginal('secret'));
        $this->assertSame('token-fonnte', $row->secret);
        $this->assertNull($row->status_uji);
    }

    public function test_uji_fonnte_wajib_konfirmasi_dan_mencatat_diterima_api(): void
    {
        $this->fonnteAktif();

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['123'], 'detail' => 'success! message in queue'], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('integrasi.fonnte.uji'), ['nomor' => '081234567890'])
            ->assertSessionHasErrors('konfirmasi');

        Http::assertNothingSent();

        $this->actingAs($this->admin())
            ->post(route('integrasi.fonnte.uji'), ['nomor' => '0812-3456-7890', 'konfirmasi' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081234567890'
            && $request->hasHeader('Authorization', 'token-fonnte'));

        $row = PengaturanIntegrasi::query()->where('layanan', 'fonnte')->firstOrFail();
        $this->assertSame(PengaturanIntegrasi::DITERIMA_API, $row->status_uji);
    }

    public function test_uji_fonnte_gagal_mencatat_status_gagal(): void
    {
        $this->fonnteAktif();

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'token invalid'], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('integrasi.fonnte.uji'), ['nomor' => '081234567890', 'konfirmasi' => '1'])
            ->assertSessionHasErrors('nomor');

        $row = PengaturanIntegrasi::query()->where('layanan', 'fonnte')->firstOrFail();
        $this->assertSame(PengaturanIntegrasi::GAGAL, $row->status_uji);
        $this->assertStringContainsString('token invalid', $row->status_uji_pesan);
    }

    public function test_audit_integrasi_tidak_menyimpan_secret(): void
    {
        $this->captchaAktif('rahasia-audit');

        $row = LogAudit::query()->where('aksi', 'integrasi.simpan')->firstOrFail();

        $this->assertSame('pengaturan_integrasi', $row->entitas);
        $this->assertStringNotContainsString('rahasia-audit', json_encode($row->toArray()));
    }

    private function pesertaVerified(): Peserta
    {
        $this->seed(AnggotaSeeder::class);

        $rapat = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
        ]));

        (new PenyiapanPeserta)->siapkan($rapat);

        $peserta = Peserta::query()->where('rapat_id', $rapat->id)->orderBy('id')->firstOrFail();
        $peserta->anggota->update(['no_hp' => '081200000001']);

        (new VerifikasiPeserta)->verifikasi($peserta, User::factory()->create(['role' => 'helpdesk']));

        return $peserta->fresh();
    }

    public function test_kirim_undangan_wa_memakai_fonnte_saat_aktif(): void
    {
        $peserta = $this->pesertaVerified();
        $this->fonnteAktif();

        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['987'], 'detail' => 'success! message in queue'], 200),
        ]);

        $log = (new UndanganPeserta)->kirim($peserta, $this->admin(), LogUndangan::METODE_WA);

        $this->assertSame(LogUndangan::METODE_WA, $log->metode);
        $this->assertSame(LogUndangan::DITERIMA_API, $log->status);
        $this->assertSame('987', $log->provider_message_id);

        Http::assertSent(fn ($request) => $request['target'] === '081200000001'
            && str_contains($request['message'], 'Undangan Rapat Koperasi'));
    }

    public function test_kirim_undangan_wa_gagal_tertutup_saat_fonnte_belum_aktif(): void
    {
        $peserta = $this->pesertaVerified();

        Http::fake();

        $log = (new UndanganPeserta)->kirim($peserta, $this->admin(), LogUndangan::METODE_WA);

        $this->assertSame(LogUndangan::GAGAL, $log->status);
        $this->assertStringContainsString('belum aktif', (string) $log->detail_error);

        Http::assertNothingSent();

        // BR-005: kegagalan kirim tidak mengubah status Verified peserta.
        $this->assertSame(Peserta::VERIFIED, $peserta->fresh()->status_verifikasi);
    }

    public function test_kirim_undangan_wa_gagal_tanpa_nomor_hp(): void
    {
        $peserta = $this->pesertaVerified();
        $peserta->anggota->update(['no_hp' => null]);
        $this->fonnteAktif();

        Http::fake();

        $log = (new UndanganPeserta)->kirim($peserta->fresh(), $this->admin(), LogUndangan::METODE_WA);

        $this->assertSame(LogUndangan::GAGAL, $log->status);
        $this->assertStringContainsString('nomor HP', (string) $log->detail_error);

        Http::assertNothingSent();
    }
}
