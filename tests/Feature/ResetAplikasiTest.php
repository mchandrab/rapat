<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\DoorprizeHadiah;
use App\Models\LogAudit;
use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reset total aplikasi: hanya Admin, wajib frasa konfirmasi, akun dibuat ulang.
 */
class ResetAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function isiData(): void
    {
        $rapat = Rapat::create(Rapat::bawaan());
        DoorprizeHadiah::create(['rapat_id' => $rapat->id, 'nama' => 'Hadiah Uji', 'jumlah' => 1]);
        Anggota::create([
            'no_anggota' => '020006000010339',
            'nama' => 'Siti Rahmawati',
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
        ]);
        LogAudit::catat('buat', 'rapat', 1, null, ['uji' => true]);
    }

    public function test_hanya_admin_yang_boleh_membuka_dan_mereset(): void
    {
        $this->isiData();

        foreach (['helpdesk', 'scanner', 'keuangan'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('pengaturan.aplikasi'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('pengaturan.aplikasi.reset'), ['konfirmasi' => 'HAPUS SEMUA DATA'])
                ->assertForbidden();
        }

        $this->assertSame(1, Rapat::query()->count());
    }

    public function test_frasa_konfirmasi_salah_tidak_menghapus_apa_pun(): void
    {
        $this->isiData();

        $this->actingAs($this->admin())
            ->post(route('pengaturan.aplikasi.reset'), ['konfirmasi' => 'hapus semua data'])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertSame(1, Rapat::query()->count());
        $this->assertSame(1, Anggota::query()->count());
        $this->assertSame(1, LogAudit::query()->count());
    }

    public function test_reset_menghapus_semua_data_dan_membuat_ulang_akun(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('presensi/1/foto.jpg', 'isi');

        $this->isiData();

        $this->actingAs($this->admin())
            ->post(route('pengaturan.aplikasi.reset'), ['konfirmasi' => 'HAPUS SEMUA DATA'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('rapat', 0);
        $this->assertDatabaseCount('doorprize_hadiah', 0);
        $this->assertDatabaseCount('anggota', 0);
        $this->assertDatabaseCount('log_audit', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['username' => 'admin', 'role' => 'admin']);
        $this->assertGuest();
        Storage::disk('local')->assertMissing('presensi/1/foto.jpg');
    }

    public function test_halaman_menampilkan_jumlah_data_yang_akan_dihapus(): void
    {
        $this->isiData();

        $this->actingAs($this->admin())
            ->get(route('pengaturan.aplikasi'))
            ->assertOk()
            ->assertSee('Hapus semua data')
            ->assertSee('Data yang akan dihapus');
    }
}
