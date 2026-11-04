<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Rapat;
use App\Models\User;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function halamanPerRole(): array
    {
        return [
            'admin' => ['admin', ['dashboard', 'anggota.index', 'rapat.index', 'verifikasi.index', 'undangan.index', 'kas.index', 'shu.index', 'doorprize.index', 'audit.index', 'integrasi.index'], []],
            'helpdesk' => ['helpdesk', ['dashboard', 'anggota.index', 'verifikasi.index', 'undangan.index'], ['shu.index', 'integrasi.index', 'kas.index', 'audit.index', 'kas-saya', 'rekonsiliasi']],
            'scanner' => ['scanner', ['dashboard', 'kas-saya', 'sinkron'], ['anggota.index', 'verifikasi.index', 'undangan.index', 'kas.index', 'shu.index']],
            'keuangan' => ['keuangan', ['dashboard', 'kas.index', 'rekonsiliasi', 'transport'], ['anggota.index', 'shu.index', 'integrasi.index', 'verifikasi.index', 'kas-saya']],
        ];
    }

    #[DataProvider('halamanPerRole')]
    public function test_role_hanya_membuka_halamannya(string $role, array $diizinkan, array $ditolak): void
    {
        $user = User::factory()->create(['role' => $role]);

        foreach ($diizinkan as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        foreach ($ditolak as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_petugas_bisa_masuk_dan_keluar(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_dibatasi_lima_percobaan_per_menit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'salah'])
                ->assertSessionHasErrors('username');
        }

        $this->post('/login', ['username' => $user->username, 'password' => 'salah'])
            ->assertTooManyRequests();
    }

    public function test_login_memakai_username_bukan_email(): void
    {
        $user = User::factory()->create([
            'username' => 'admin.uji',
            'email' => 'legacy@erat.test',
            'password' => 'password',
        ]);

        $this->post('/login', ['email' => 'legacy@erat.test', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', ['username' => 'ADMIN.UJI', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_nonaktif_tidak_dapat_login_dan_sesi_aktif_dihentikan(): void
    {
        $user = User::factory()->create([
            'role' => 'scanner',
            'active' => false,
            'password' => 'password',
        ]);

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_helpdesk_tidak_dapat_membuka_atau_menjalankan_presensi(): void
    {
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($helpdesk)->get(route('kas-saya'))->assertForbidden();
        $this->actingAs($helpdesk)->get(route('presensi.index'))->assertForbidden();
        $this->actingAs($helpdesk)->post(route('kas.terima'))->assertForbidden();

        $this->assertDatabaseCount('presensi', 0);
        $this->assertDatabaseCount('transport_pembayaran', 0);
    }

    public function test_nba_ditampilkan_dengan_titik(): void
    {
        $this->seed(AnggotaSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('anggota.index'))
            ->assertOk()
            ->assertSee('020006.000.010.339');
    }

    public function test_data_anggota_dan_verifikasi_berada_di_satu_halaman(): void
    {
        $this->seed(AnggotaSeeder::class);
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($helpdesk)->get(route('anggota.index'))
            ->assertOk()
            ->assertSee('Semua Status Keanggotaan')
            ->assertSee('Status Verifikasi')
            ->assertSee('Simpanan Wajib');
    }

    public function test_ringkasan_rapat_dibatasi_sesuai_tugas_role(): void
    {
        Rapat::create(array_merge(Rapat::bawaan(), [
            'judul' => 'Rapat Pembagian Tugas',
            'status' => Rapat::VERIFIKASI,
            'mode_presensi' => '2x',
            'mode_kelayakan' => 'A',
            'transport_dasar' => 125000,
        ]));

        $helpdesk = User::factory()->create(['role' => 'helpdesk']);
        $this->actingAs($helpdesk)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kriteria kelayakan')
            ->assertDontSee('Konfigurasi transport');

        $scanner = User::factory()->create(['role' => 'scanner']);
        $this->actingAs($scanner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mode Presensi 2x (Datang–Pulang)')
            ->assertSee('Gerbang presensi')
            ->assertDontSee('Kriteria kelayakan')
            ->assertDontSee('Konfigurasi transport');

        $keuangan = User::factory()->create(['role' => 'keuangan']);
        $this->actingAs($keuangan)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Konfigurasi transport')
            ->assertSee('Rp125.000')
            ->assertDontSee('Kriteria kelayakan');
    }

    public function test_helpdesk_tidak_dapat_mengubah_master_dan_keuangan_tidak_dapat_membukanya(): void
    {
        $anggota = Anggota::factory()->create();
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($helpdesk)->put(route('anggota.update', $anggota), [
            'nama' => 'Perubahan tanpa izin',
        ])->assertForbidden();

        $keuangan = User::factory()->create(['role' => 'keuangan']);
        $this->actingAs($keuangan)->get(route('anggota.index'))->assertForbidden();

        $this->assertNotSame('Perubahan tanpa izin', $anggota->refresh()->nama);
    }

    public function test_halaman_integrasi_tidak_menampilkan_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('integrasi.index'))
            ->assertOk()
            ->assertDontSee('secret_ciphertext');
    }

    public function test_sidebar_admin_dibagi_per_bagian_dan_pengaturan_menjadi_satu_menu(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Utama', 'Operasional', 'Pelaporan', 'Sistem'])
            ->assertSee('Pengaturan')
            ->assertSee(route('pengguna.index'), false)
            ->assertDontSee('nav-sistem-pengaturan');

        $this->actingAs($admin)->get(route('integrasi.index'))
            ->assertOk()
            ->assertSee('nav-overflow', false)
            ->assertSee('nav nav-pills flex-nowrap erat-settings-nav', false)
            ->assertSeeInOrder(['Pengguna', 'Integrasi', 'Aplikasi'])
            ->assertSee('nav-link active', false);
    }

    public function test_semua_halaman_pengaturan_memakai_navbar_horizontal_yang_sama(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['pengguna.index', 'integrasi.index', 'pengaturan.aplikasi'] as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee('aria-label="Navigasi pengaturan"', false)
                ->assertSee('nav-overflow', false)
                ->assertSeeInOrder(['Pengguna', 'Integrasi', 'Aplikasi']);
        }
    }

    public function test_sidebar_role_lain_memakai_bagian_sesuai_tugas(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);
        $this->actingAs($scanner)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Utama', 'Operasional'])
            ->assertSee('Status Koneksi')
            ->assertDontSee('Pengaturan');

        $keuangan = User::factory()->create(['role' => 'keuangan']);
        $this->actingAs($keuangan)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Utama', 'Kas &amp; Transport', 'Pelaporan'], false)
            ->assertDontSee('Pengaturan');
    }
}
