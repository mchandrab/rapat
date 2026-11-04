<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D-032: aplikasi berjalan online saja. Saat koneksi putus pemindaian
 * dihentikan total, tanpa antrean offline dan tanpa pencatatan manual.
 */
class OfflineTest extends TestCase
{
    use RefreshDatabase;

    private function scanner(): User
    {
        return User::factory()->create(['role' => 'scanner']);
    }

    public function test_halaman_pindai_menampilkan_peringatan_koneksi_putus(): void
    {
        $this->actingAs($this->scanner())
            ->get(route('kas-saya'))
            ->assertOk()
            ->assertSee('Koneksi terputus')
            ->assertSee('D-032')
            ->assertSee('navigator.onLine', false)
            ->assertSee('statusKoneksi', false)
            ->assertSee('tombolPresensi', false);
    }

    public function test_halaman_status_koneksi_menjelaskan_aturan_offline(): void
    {
        $this->actingAs($this->scanner())
            ->get(route('sinkron'))
            ->assertOk()
            ->assertSee('Aplikasi berjalan online saja')
            ->assertSee('D-032')
            ->assertSee('Antrean offline')
            ->assertSee('Tidak dipakai')
            ->assertDontSee('menunggu keputusan O-002');
    }

    public function test_role_lain_tidak_melihat_halaman_scanner(): void
    {
        foreach (['admin', 'helpdesk', 'keuangan'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('sinkron'))
                ->assertForbidden();
        }
    }
}
