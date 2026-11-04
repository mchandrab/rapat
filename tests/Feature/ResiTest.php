<?php

namespace Tests\Feature;

use App\Models\Peserta;
use App\Models\Rapat;
use App\Models\TransportPembayaran;
use App\Models\User;
use App\Services\AlokasiKas;
use App\Services\PenyiapanPeserta;
use App\Services\PresensiScan;
use App\Services\VerifikasiPeserta;
use Carbon\Carbon;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 5.7: resi transport hanya untuk Scanner pembayar, Admin, dan Keuangan.
 */
class ResiTest extends TestCase
{
    use RefreshDatabase;

    private Rapat $rapat;

    private TransportPembayaran $bayar;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 08:30:00');
        Storage::fake('local');

        $this->seed(AnggotaSeeder::class);

        $this->rapat = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
            'jam_mulai_presensi' => '07:00',
            'jam_mulai_rat' => '08:00',
            'toleransi_menit' => 60,
            'transport_dasar' => 100000,
        ]));

        (new PenyiapanPeserta)->siapkan($this->rapat);

        $peserta = Peserta::query()->where('rapat_id', $this->rapat->id)->orderBy('id')->firstOrFail();
        $verifikasi = new VerifikasiPeserta;
        $verifikasi->verifikasi($peserta, User::factory()->create(['role' => 'helpdesk']));
        $token = $verifikasi->token($peserta->fresh());

        $this->scanner = User::factory()->create(['role' => 'scanner', 'name' => 'Scanner Satu']);
        $kas = (new AlokasiKas)->serahkan($this->rapat, $this->scanner, User::factory()->create(['role' => 'keuangan']), 500000);
        (new AlokasiKas)->terima($kas, $this->scanner);
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $hasil = (new PresensiScan)->catat(
            $this->rapat,
            $token,
            $this->scanner,
            UploadedFile::fake()->image('foto.jpg'),
            'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('tte.png')->getContent()),
        );

        $this->assertTrue($hasil['ok'], $hasil['pesan']);

        $this->bayar = TransportPembayaran::query()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function sebagai(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_scanner_pembayar_melihat_resi(): void
    {
        $this->actingAs($this->scanner)
            ->get(route('resi.show', $this->bayar->kode_resi))
            ->assertOk()
            ->assertSee($this->bayar->kode_resi)
            ->assertSee('Budi Santoso')
            ->assertSee('Rp100.000')
            ->assertSee('Cetak resi');
    }

    public function test_admin_dan_keuangan_boleh_melihat_resi(): void
    {
        foreach (['admin', 'keuangan'] as $role) {
            $this->actingAs($this->sebagai($role))
                ->get(route('resi.show', $this->bayar->kode_resi))
                ->assertOk();
        }
    }

    public function test_scanner_lain_dan_helpdesk_ditolak(): void
    {
        $this->actingAs($this->sebagai('scanner'))
            ->get(route('resi.show', $this->bayar->kode_resi))
            ->assertForbidden();

        $this->actingAs($this->sebagai('helpdesk'))
            ->get(route('resi.show', $this->bayar->kode_resi))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('resi.show', $this->bayar->kode_resi))
            ->assertRedirect(route('login'));
    }

    public function test_kode_resi_tidak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs($this->scanner)
            ->get(route('resi.show', 'TRX000000ZZZZZZ'))
            ->assertNotFound();
    }
}
