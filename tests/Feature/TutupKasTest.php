<?php

namespace Tests\Feature;

use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\AlokasiKas;
use App\Services\PenyiapanPeserta;
use App\Services\PresensiScan;
use App\Services\TutupKas;
use App\Services\VerifikasiPeserta;
use Carbon\Carbon;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * BR-009 / D-026: konfirmasi terima kas oleh Scanner, tutup kas per Scanner,
 * dan rekonsiliasi sisa fisik (selisih bukan nol wajib beralasan).
 */
class TutupKasTest extends TestCase
{
    use RefreshDatabase;

    private Rapat $rapat;

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
            'presensi_dibuka_at' => now(),
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

    private function keuangan(): User
    {
        return User::factory()->create(['role' => 'keuangan', 'name' => 'Petugas Keuangan']);
    }

    private function alokasi(User $scanner, float $nominal = 500000): KasScanner
    {
        return (new AlokasiKas)->serahkan($this->rapat, $scanner, $this->keuangan(), $nominal);
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

    public function test_alokasi_baru_menunggu_konfirmasi_scanner(): void
    {
        $scanner = $this->scanner();
        $kas = $this->alokasi($scanner);

        $this->assertSame(KasScanner::MENUNGGU, $kas->status);
        $this->assertNull($kas->diterima_pada);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'alokasi', 'entitas' => 'kas_scanner']);

        $this->actingAs($scanner);

        Livewire::test('presensi-scan')
            ->assertSee('Saya sudah menerima kas')
            ->assertSee('Rp500.000');
    }

    public function test_scan_ditolak_sebelum_kas_dikonfirmasi(): void
    {
        $scanner = $this->scanner();
        $this->alokasi($scanner);
        $token = $this->token($this->peserta());

        $hasil = (new PresensiScan)->catat(
            $this->rapat, $token, $scanner,
            UploadedFile::fake()->image('foto.jpg'), $this->tte(),
        );

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Saya sudah menerima kas', $hasil['pesan']);
        $this->assertSame(0, Presensi::query()->count());
    }

    public function test_scanner_mengonfirmasi_terima_kas_sekali_dan_masuk_audit(): void
    {
        $scanner = $this->scanner();
        $kas = $this->alokasi($scanner);

        (new AlokasiKas)->terima($kas, $scanner);

        $this->assertSame(KasScanner::AKTIF, $kas->fresh()->status);
        $this->assertNotNull($kas->fresh()->diterima_pada);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'terima_kas', 'entitas' => 'kas_scanner']);

        $this->expectException(LogicException::class);
        (new AlokasiKas)->terima($kas->fresh(), $scanner);
    }

    public function test_scanner_lain_tidak_boleh_mengonfirmasi_kas_orang_lain(): void
    {
        $pemilik = $this->scanner('Scanner Pemilik');
        $lain = $this->scanner('Scanner Lain');
        $kas = $this->alokasi($pemilik);

        $this->expectException(LogicException::class);
        (new AlokasiKas)->terima($kas, $lain);
    }

    public function test_konfirmasi_lewat_route_hanya_untuk_pemilik_dan_role_scanner(): void
    {
        $pemilik = $this->scanner('Scanner Pemilik');
        $lain = $this->scanner('Scanner Lain');
        $kas = $this->alokasi($pemilik);

        $this->actingAs($this->keuangan())
            ->post(route('kas.terima'))
            ->assertForbidden();

        $this->actingAs($lain)
            ->post(route('kas.terima'))
            ->assertSessionHasErrors('kas');

        $this->assertSame(KasScanner::MENUNGGU, $kas->fresh()->status);

        $this->actingAs($pemilik)
            ->post(route('kas.terima'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(KasScanner::AKTIF, $kas->fresh()->status);
    }

    public function test_tutup_kas_tanpa_selisih_tidak_mewajibkan_catatan(): void
    {
        $scanner = $this->scanner();
        $keuangan = $this->keuangan();
        $kas = $this->alokasi($scanner);

        (new TutupKas)->tutup($kas, $keuangan, 500000, null);

        $kas->refresh();

        $this->assertSame(KasScanner::DITUTUP, $kas->status);
        $this->assertSame('500000.00', $kas->sisa_fisik);
        $this->assertSame($keuangan->id, $kas->ditutup_oleh);
        $this->assertNotNull($kas->ditutup_pada);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'tutup_kas', 'entitas' => 'kas_scanner']);
    }

    public function test_selisih_wajib_beralasan(): void
    {
        $kas = $this->alokasi($this->scanner());

        $this->expectException(LogicException::class);
        (new TutupKas)->tutup($kas, $this->keuangan(), 450000, '   ');
    }

    public function test_selisih_dengan_alasan_tersimpan_dan_diaudit(): void
    {
        $kas = $this->alokasi($this->scanner());

        (new TutupKas)->tutup($kas, $this->keuangan(), 450000, 'Uang kurang saat dihitung bersama Scanner');

        $kas->refresh();

        $this->assertSame(KasScanner::DITUTUP, $kas->status);
        $this->assertSame('450000.00', $kas->sisa_fisik);
        $this->assertSame('Uang kurang saat dihitung bersama Scanner', $kas->catatan_selisih);

        $audit = LogAudit::query()->where('aksi', 'tutup_kas')->sole();

        $this->assertSame('Uang kurang saat dihitung bersama Scanner', $audit->alasan);
        $this->assertSame(-50000.0, (float) $audit->sesudah['selisih']);
    }

    public function test_kas_tidak_dapat_ditutup_dua_kali(): void
    {
        $kas = $this->alokasi($this->scanner());
        $keuangan = $this->keuangan();

        (new TutupKas)->tutup($kas, $keuangan, 500000, null);

        $this->expectException(LogicException::class);
        (new TutupKas)->tutup($kas->fresh(), $keuangan, 500000, null);
    }

    public function test_scan_ditolak_setelah_kas_ditutup(): void
    {
        $scanner = $this->scanner();
        $kas = $this->alokasi($scanner);
        (new AlokasiKas)->terima($kas, $scanner);
        (new TutupKas)->tutup($kas->fresh(), $this->keuangan(), 500000, null);

        $hasil = (new PresensiScan)->catat(
            $this->rapat, $this->token($this->peserta()), $scanner,
            UploadedFile::fake()->image('foto.jpg'), $this->tte(),
        );

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('sudah ditutup', $hasil['pesan']);
        $this->assertSame(0, Presensi::query()->count());
    }

    public function test_halaman_rekonsiliasi_menampilkan_kas_dan_hanya_untuk_keuangan(): void
    {
        $scanner = $this->scanner('Scanner Satu');
        $kas = $this->alokasi($scanner);
        (new AlokasiKas)->terima($kas, $scanner);

        $this->actingAs($this->keuangan())
            ->get(route('rekonsiliasi'))
            ->assertOk()
            ->assertSee('Scanner Satu')
            ->assertSee('Rp500.000')
            ->assertSee('Tutup kas');

        foreach (['admin', 'helpdesk', 'scanner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('rekonsiliasi'))
                ->assertForbidden();
        }
    }

    public function test_tutup_lewat_route_menyimpan_hasil_rekonsiliasi(): void
    {
        $scanner = $this->scanner();
        $kas = $this->alokasi($scanner);
        (new AlokasiKas)->terima($kas, $scanner);

        $this->actingAs($this->keuangan())
            ->post(route('kas.tutup', $kas), ['sisa_fisik' => 480000, 'catatan' => 'Kurang Rp20.000, sudah dikonfirmasi Scanner'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $kas->refresh();

        $this->assertSame(KasScanner::DITUTUP, $kas->status);
        $this->assertSame('480000.00', $kas->sisa_fisik);

        $this->actingAs($this->scanner())
            ->post(route('kas.tutup', $kas), ['sisa_fisik' => 480000])
            ->assertForbidden();
    }
}
