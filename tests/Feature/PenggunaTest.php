<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D-035: Admin mengelola akun petugas; role tetap empat, akun sendiri dilindungi.
 */
class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_role_selain_admin_tidak_boleh_membuka_atau_mengubah_pengguna(): void
    {
        foreach (['helpdesk', 'scanner', 'keuangan'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('pengguna.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('pengguna.store'), [
                    'name' => 'Penyusup',
                    'username' => 'penyusup',
                    'role' => 'admin',
                    'password' => 'rahasia123',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['username' => 'penyusup']);
    }

    public function test_admin_melihat_daftar_pengguna(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('pengguna.index'))
            ->assertOk()
            ->assertSee('Tambah pengguna')
            ->assertSee($admin->username);

        $this->actingAs($admin)
            ->get(route('pengguna.edit', $admin))
            ->assertOk()
            ->assertSee('Ubah akun '.$admin->name);
    }

    public function test_admin_menambah_pengguna_dan_mencatat_audit(): void
    {
        $this->actingAs($this->admin())
            ->post(route('pengguna.store'), [
                'name' => 'Andi Scanner',
                'username' => 'scanner2',
                'role' => 'scanner',
                'password' => 'rahasia123',
            ])
            ->assertSessionHasNoErrors();

        $baru = User::query()->where('username', 'scanner2')->firstOrFail();
        $this->assertSame('scanner', $baru->role);
        $this->assertTrue(Hash::check('rahasia123', $baru->password));

        $audit = LogAudit::query()->where('entitas', 'user')->where('aksi', 'buat')->firstOrFail();
        $this->assertSame($baru->id, $audit->entitas_id);
        $this->assertArrayNotHasKey('password', $audit->sesudah);
    }

    public function test_username_ganda_ditolak_tanpa_membedakan_huruf_besar(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('pengguna.store'), [
            'name' => 'Kembar',
            'username' => strtoupper($admin->username),
            'role' => 'helpdesk',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');
    }

    public function test_username_dengan_karakter_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin())->post(route('pengguna.store'), [
            'name' => 'Username Salah',
            'username' => 'nama petugas!',
            'role' => 'helpdesk',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['name' => 'Username Salah']);
        $this->assertDatabaseMissing('log_audit', ['entitas' => 'user', 'aksi' => 'buat']);
    }

    public function test_admin_mengubah_data_dan_mengganti_sandi(): void
    {
        $petugas = User::factory()->create([
            'name' => 'Nama Lama',
            'username' => 'helpdesk2',
            'role' => 'helpdesk',
        ]);

        $this->actingAs($this->admin())
            ->put(route('pengguna.update', $petugas), [
                'name' => 'Nama Baru',
                'username' => 'helpdesk.baru',
                'role' => 'keuangan',
                'password' => 'sandibaru123',
            ])
            ->assertRedirect(route('pengguna.index'));

        $petugas->refresh();
        $this->assertSame('Nama Baru', $petugas->name);
        $this->assertSame('helpdesk.baru', $petugas->username);
        $this->assertSame('keuangan', $petugas->role);
        $this->assertTrue(Hash::check('sandibaru123', $petugas->password));
        $this->assertSame(1, LogAudit::query()->where('aksi', 'ubah')->count());
    }

    public function test_sandi_tidak_berubah_bila_kolom_dibiarkan_kosong(): void
    {
        $petugas = User::factory()->create([
            'username' => 'scanner3',
            'role' => 'scanner',
            'password' => 'sandilama123',
        ]);

        $this->actingAs($this->admin())
            ->put(route('pengguna.update', $petugas), [
                'name' => 'Tetap',
                'username' => 'scanner3',
                'role' => 'scanner',
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandilama123', $petugas->refresh()->password));
    }

    public function test_admin_tidak_dapat_menghapus_atau_menurunkan_akun_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $admin))
            ->assertSessionHasErrors('pengguna');

        $this->actingAs($admin)
            ->put(route('pengguna.update', $admin), [
                'name' => $admin->name,
                'username' => $admin->username,
                'role' => 'helpdesk',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_admin_menghapus_pengguna_lain_dan_mencatat_audit(): void
    {
        $petugas = User::factory()->create(['username' => 'scanner4', 'role' => 'scanner']);

        $this->actingAs($this->admin())
            ->delete(route('pengguna.destroy', $petugas))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $petugas->id]);
        $this->assertSame(1, LogAudit::query()->where('aksi', 'hapus')->count());
    }
}
