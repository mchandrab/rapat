<?php

namespace Tests\Feature;

use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\PenyiapanPeserta;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 5.8: bukti foto/TTE hanya untuk Admin & Keuangan, selalu dari disk privat.
 */
class BuktiPresensiTest extends TestCase
{
    use RefreshDatabase;

    private Presensi $presensi;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(AnggotaSeeder::class);

        $rapat = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
        ]));

        (new PenyiapanPeserta)->siapkan($rapat);

        $peserta = Peserta::query()->where('rapat_id', $rapat->id)->orderBy('id')->firstOrFail();

        Storage::disk('local')->put('presensi/1/1/foto.jpg', 'isi-foto');
        Storage::disk('local')->put('presensi/1/1/tte.png', 'isi-tte');

        $this->presensi = Presensi::create([
            'peserta_id' => $peserta->id,
            'datang_at' => '2026-11-15 07:10:00',
            'dicatat_oleh' => User::factory()->create(['role' => 'scanner'])->id,
            'foto_path' => 'presensi/1/1/foto.jpg',
            'tte_path' => 'presensi/1/1/tte.png',
            'nominal_transport_final' => 100000,
        ]);
    }

    private function sebagai(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_admin_dan_keuangan_membuka_foto_dan_tanda_tangan(): void
    {
        foreach (['admin', 'keuangan'] as $role) {
            foreach (['foto' => 'isi-foto', 'tte' => 'isi-tte'] as $jenis => $isi) {
                $this->actingAs($this->sebagai($role))
                    ->get(route('presensi.bukti', [$this->presensi, $jenis]))
                    ->assertOk()
                    ->assertStreamedContent($isi);
            }
        }
    }

    public function test_helpdesk_dan_scanner_ditolak(): void
    {
        foreach (['helpdesk', 'scanner'] as $role) {
            $this->actingAs($this->sebagai($role))
                ->get(route('presensi.bukti', [$this->presensi, 'foto']))
                ->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('presensi.bukti', [$this->presensi, 'foto']))
            ->assertRedirect(route('login'));
    }

    public function test_jenis_bukti_tidak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs($this->sebagai('admin'))
            ->get(route('presensi.bukti', [$this->presensi, 'lainnya']))
            ->assertNotFound();
    }

    public function test_berkas_hilang_menghasilkan_404(): void
    {
        Storage::disk('local')->delete('presensi/1/1/foto.jpg');

        $this->actingAs($this->sebagai('admin'))
            ->get(route('presensi.bukti', [$this->presensi, 'foto']))
            ->assertNotFound();
    }

    public function test_halaman_daftar_presensi_menampilkan_tautan_bukti(): void
    {
        $this->actingAs($this->sebagai('keuangan'))
            ->get(route('presensi.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Rp100.000')
            ->assertSee('Tanda tangan');

        $this->actingAs($this->sebagai('helpdesk'))
            ->get(route('presensi.index'))
            ->assertForbidden();
    }
}
