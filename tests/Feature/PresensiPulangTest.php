<?php

namespace Tests\Feature;

use App\Models\KasScanner;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\AlokasiKas;
use App\Services\Kuorum;
use App\Services\PenyiapanPeserta;
use App\Services\PresensiScan;
use App\Services\VerifikasiPeserta;
use Carbon\Carbon;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * D-034: Mode Presensi 2x (Datang–Pulang).
 * Kedatangan mengunci nominal tanpa membayar; pulang membayar dari kas Scanner
 * yang mencatat pulang, boleh berbeda dari pencatat datang.
 */
class PresensiPulangTest extends TestCase
{
    use RefreshDatabase;

    private Rapat $rapat;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-15 08:30:00');

        Storage::fake('local');
        Storage::fake('tmp-for-tests');

        $this->seed(AnggotaSeeder::class);

        $this->rapat = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
            'jam_mulai_presensi' => '07:00',
            'jam_mulai_rat' => '08:00',
            'toleransi_menit' => 60,
            'transport_dasar' => 100000,
            'mode_presensi' => '2x',
        ]));

        (new PenyiapanPeserta)->siapkan($this->rapat);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scanner(string $nama = 'Scanner Satu'): User
    {
        return User::factory()->create(['role' => 'scanner', 'name' => $nama]);
    }

    private function kas(User $scanner, float $nominal = 500000): KasScanner
    {
        $keuangan = User::factory()->create(['role' => 'keuangan']);

        return (new AlokasiKas)->terima(
            (new AlokasiKas)->serahkan($this->rapat, $scanner, $keuangan, $nominal),
            $scanner,
        );
    }

    private function token(Peserta $peserta): string
    {
        $layanan = new VerifikasiPeserta;
        $layanan->verifikasi($peserta, User::factory()->create(['role' => 'helpdesk']));

        return $layanan->token($peserta->fresh());
    }

    private function peserta(int $ke = 0): Peserta
    {
        return $this->rapat->peserta()->with('anggota')->orderBy('id')->skip($ke)->firstOrFail();
    }

    private function tte(): string
    {
        return 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('tte.png')->getContent());
    }

    private function datang(string $token, User $scanner): array
    {
        return (new PresensiScan)->catat(
            $this->rapat,
            $token,
            $scanner,
            UploadedFile::fake()->image('foto.jpg'),
            $this->tte(),
        );
    }

    public function test_kedatangan_mode_2x_tanpa_kas_dan_tanpa_pembayaran(): void
    {
        $scanner = $this->scanner();
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $hasil = $this->datang($token, $scanner);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $this->assertSame(100000.0, $hasil['nominal']);
        $this->assertStringContainsString('saat pulang', $hasil['pesan']);

        $presensi = Presensi::query()->where('peserta_id', $peserta->id)->firstOrFail();

        $this->assertNull($presensi->pulang_at);
        $this->assertNull($presensi->kode_resi);
        $this->assertSame('100000.00', $presensi->nominal_transport_final);
        $this->assertDatabaseCount('transport_pembayaran', 0);
    }

    public function test_kedatangan_ganda_mengarahkan_ke_tab_pulang(): void
    {
        $scanner = $this->scanner();
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->datang($token, $scanner);
        $kedua = $this->datang($token, $scanner);

        $this->assertFalse($kedua['ok']);
        $this->assertStringContainsString('Gunakan tab Pulang', $kedua['pesan']);
        $this->assertDatabaseCount('presensi', 1);
    }

    public function test_pulang_dibayar_dari_kas_scanner_berbeda(): void
    {
        $datang = $this->scanner('Scanner Datang');
        $pulang = $this->scanner('Scanner Pulang');
        $kasPulang = $this->kas($pulang);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->assertTrue($this->datang($token, $datang)['ok']);

        $hasil = (new PresensiScan)->pulang($this->rapat, $token, $pulang);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $this->assertNotNull($hasil['presensi']->pulang_at);
        $this->assertNotNull($hasil['presensi']->kode_resi);

        $this->assertDatabaseHas('transport_pembayaran', [
            'peserta_id' => $peserta->id,
            'kas_scanner_id' => $kasPulang->id,
            'dibayar_oleh' => $pulang->id,
            'nominal' => 100000,
        ]);

        $this->assertSame(400000.0, $kasPulang->fresh()->sisa());
        $this->assertDatabaseHas('log_audit', ['aksi' => 'pulang', 'entitas' => 'presensi']);
    }

    public function test_pulang_ganda_ditolak(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->datang($token, $scanner);
        $this->assertTrue((new PresensiScan)->pulang($this->rapat, $token, $scanner)['ok']);

        $kedua = (new PresensiScan)->pulang($this->rapat, $token, $scanner);

        $this->assertFalse($kedua['ok']);
        $this->assertStringContainsString('sudah tercatat pulang', $kedua['pesan']);
        $this->assertDatabaseCount('transport_pembayaran', 1);
    }

    public function test_pulang_sebelum_datang_ditolak(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $hasil = (new PresensiScan)->pulang($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum tercatat datang', $hasil['pesan']);
        $this->assertDatabaseCount('transport_pembayaran', 0);
    }

    public function test_pulang_ditolak_bila_kas_tidak_cukup_dan_tidak_mencatat_pulang(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner, 50000);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->datang($token, $scanner);
        $hasil = (new PresensiScan)->pulang($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('tidak cukup', $hasil['pesan']);
        $this->assertNull(Presensi::query()->where('peserta_id', $peserta->id)->firstOrFail()->pulang_at);
        $this->assertDatabaseCount('transport_pembayaran', 0);
    }

    public function test_mode_1x_tidak_punya_presensi_pulang(): void
    {
        $this->rapat->update(['mode_presensi' => '1x']);

        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $hasil = (new PresensiScan)->pulang($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Mode Presensi 1x', $hasil['pesan']);
    }

    public function test_kuorum_tetap_menghitung_peserta_yang_belum_pulang(): void
    {
        $scanner = $this->scanner();
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->datang($token, $scanner);

        $this->assertSame(1, (new Kuorum)->hitung($this->rapat->fresh())['hadir']);
    }

    public function test_livewire_menyediakan_tab_pulang_dan_membayar(): void
    {
        $datang = $this->scanner('Scanner Datang');
        $pulang = $this->scanner('Scanner Pulang');
        $this->kas($pulang);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi(User::factory()->create(['role' => 'admin']));

        $this->datang($token, $datang);

        $this->actingAs($pulang);

        $komponen = Livewire::test('presensi-scan')
            ->assertSee('1. Datang')
            ->set('mode', 'pulang')
            ->assertSee('Catat Pulang')
            ->set('token', $token)
            ->call('scan');

        $this->assertTrue($komponen->instance()->berhasil);
        $this->assertNotNull($komponen->instance()->resiTerakhir);
        $this->assertNotNull(Presensi::query()->where('peserta_id', $peserta->id)->firstOrFail()->pulang_at);
    }
}
