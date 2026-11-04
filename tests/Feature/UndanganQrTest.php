<?php

namespace Tests\Feature;

use App\Models\Peserta;
use App\Models\Rapat;
use App\Models\User;
use App\Services\PenyiapanPeserta;
use App\Services\VerifikasiPeserta;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.5a: undangan cetak berisi QR dari token rapat yang sama (BR-005).
 */
class UndanganQrTest extends TestCase
{
    use RefreshDatabase;

    private Peserta $peserta;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AnggotaSeeder::class);

        $rapat = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
        ]));

        (new PenyiapanPeserta)->siapkan($rapat);

        $this->peserta = Peserta::query()->where('rapat_id', $rapat->id)->orderBy('id')->firstOrFail();

        $verifikasi = new VerifikasiPeserta;
        $verifikasi->verifikasi($this->peserta, User::factory()->create(['role' => 'helpdesk']));

        $this->token = $verifikasi->token($this->peserta->fresh());
    }

    private function sebagai(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_admin_dan_helpdesk_melihat_undangan_berisi_qr(): void
    {
        foreach (['admin', 'helpdesk'] as $role) {
            $this->actingAs($this->sebagai($role))
                ->get(route('undangan.cetak', $this->peserta))
                ->assertOk()
                ->assertSee('Budi Santoso')
                ->assertSee('<svg', false)
                ->assertSee($this->token, false)
                ->assertSee('Cetak undangan');
        }
    }

    public function test_scanner_dan_keuangan_ditolak(): void
    {
        foreach (['scanner', 'keuangan'] as $role) {
            $this->actingAs($this->sebagai($role))
                ->get(route('undangan.cetak', $this->peserta))
                ->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('undangan.cetak', $this->peserta))
            ->assertRedirect(route('login'));
    }

    public function test_peserta_belum_verified_tidak_punya_undangan(): void
    {
        $lain = Peserta::query()
            ->where('rapat_id', $this->peserta->rapat_id)
            ->where('id', '!=', $this->peserta->id)
            ->firstOrFail();

        $this->actingAs($this->sebagai('admin'))
            ->get(route('undangan.cetak', $lain))
            ->assertNotFound();
    }

    public function test_qr_berbeda_untuk_peserta_berbeda(): void
    {
        $lain = Peserta::query()
            ->where('rapat_id', $this->peserta->rapat_id)
            ->where('id', '!=', $this->peserta->id)
            ->firstOrFail();

        (new VerifikasiPeserta)->verifikasi($lain, User::factory()->create(['role' => 'helpdesk']));

        $admin = $this->sebagai('admin');

        $satu = $this->actingAs($admin)->get(route('undangan.cetak', $this->peserta))->getContent();
        $dua = $this->actingAs($admin)->get(route('undangan.cetak', $lain))->getContent();

        $this->assertNotSame($satu, $dua);
    }
}
