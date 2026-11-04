<?php

namespace Tests\Feature;

use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\Rapat;
use App\Models\User;
use App\Services\AlokasiKas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class KasTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), $ubah));
    }

    private function scanner(string $nama = 'Scanner Satu'): User
    {
        return User::factory()->create(['role' => 'scanner', 'name' => $nama]);
    }

    private function keuangan(): User
    {
        return User::factory()->create(['role' => 'keuangan', 'name' => 'Petugas Keuangan']);
    }

    public function test_keuangan_mencatat_alokasi_kas_dan_audit(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $scanner = $this->scanner();

        $this->actingAs($this->keuangan())
            ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 1500000])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('kas_scanner', [
            'rapat_id' => $rapat->id,
            'scanner_user_id' => $scanner->id,
            'kas_awal' => 1500000,
            // D-026: alokasi baru berstatus menunggu sampai Scanner mengonfirmasi terima.
            'status' => KasScanner::MENUNGGU,
        ]);

        $this->assertDatabaseHas('log_audit', ['aksi' => 'alokasi', 'entitas' => 'kas_scanner']);
    }

    public function test_alokasi_kedua_ditolak_tanpa_top_up(): void
    {
        $rapat = $this->rapat();
        $scanner = $this->scanner();
        $keuangan = $this->keuangan();

        (new AlokasiKas)->serahkan($rapat, $scanner, $keuangan, 1000000);

        $this->actingAs($keuangan)
            ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 500000])
            ->assertSessionHasErrors('kas');

        $this->assertDatabaseCount('kas_scanner', 1);
        $this->assertSame('1000000.00', KasScanner::query()->firstOrFail()->kas_awal);
    }

    public function test_scanner_dan_admin_tidak_boleh_mengalokasikan(): void
    {
        $rapat = $this->rapat();
        $scanner = $this->scanner();

        $this->actingAs($scanner)
            ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 1000000])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 1000000])
            ->assertForbidden();

        $this->assertDatabaseCount('kas_scanner', 0);
    }

    public function test_alokasi_ditolak_untuk_rapat_selesai_atau_dibatalkan(): void
    {
        $scanner = $this->scanner();
        $keuangan = $this->keuangan();

        foreach ([Rapat::SELESAI, Rapat::DIBATALKAN] as $status) {
            $rapat = $this->rapat(['status' => $status]);

            $this->actingAs($keuangan)
                ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 1000000])
                ->assertSessionHasErrors('kas');

            $this->assertSame(0, KasScanner::query()->where('rapat_id', $rapat->id)->count());
        }
    }

    public function test_penerima_kas_harus_akun_scanner_dan_nominal_positif(): void
    {
        $this->rapat();
        $keuangan = $this->keuangan();
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($keuangan)
            ->post(route('kas.store'), ['scanner_user_id' => $helpdesk->id, 'kas_awal' => 1000000])
            ->assertSessionHasErrors('scanner_user_id');

        $this->actingAs($keuangan)
            ->post(route('kas.store'), ['scanner_user_id' => $this->scanner()->id, 'kas_awal' => 0])
            ->assertSessionHasErrors('kas_awal');

        $this->assertDatabaseCount('kas_scanner', 0);
    }

    public function test_scanner_nonaktif_tidak_dapat_menerima_alokasi_kas_baru(): void
    {
        $this->rapat();
        $scanner = $this->scanner();
        $scanner->update(['active' => false]);

        $this->actingAs($this->keuangan())
            ->post(route('kas.store'), ['scanner_user_id' => $scanner->id, 'kas_awal' => 1000000])
            ->assertSessionHasErrors('scanner_user_id');

        $this->assertDatabaseCount('kas_scanner', 0);
    }

    public function test_satu_scanner_satu_rapat_tidak_bisa_digandakan_meski_request_bersamaan(): void
    {
        $rapat = $this->rapat();
        $scanner = $this->scanner();
        $keuangan = $this->keuangan();

        (new AlokasiKas)->serahkan($rapat, $scanner, $keuangan, 1000000);

        $this->expectException(LogicException::class);
        (new AlokasiKas)->serahkan($rapat, $scanner, $keuangan, 1000000);
    }

    public function test_kas_berlaku_per_rapat(): void
    {
        $lama = $this->rapat(['judul' => 'RAT Lama']);
        $baru = $this->rapat(['judul' => 'RAT Baru']);
        $scanner = $this->scanner();
        $keuangan = $this->keuangan();

        (new AlokasiKas)->serahkan($lama, $scanner, $keuangan, 1000000);
        (new AlokasiKas)->serahkan($baru, $scanner, $keuangan, 2000000);

        $this->assertDatabaseCount('kas_scanner', 2);
    }

    public function test_halaman_kas_menampilkan_alokasi_nyata_dan_admin_hanya_memantau(): void
    {
        $rapat = $this->rapat(['judul' => 'RAT Tahun Buku Uji']);
        $scanner = $this->scanner('Scanner Melati');
        (new AlokasiKas)->serahkan($rapat, $scanner, $this->keuangan(), 1250000);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('kas.index'))
            ->assertOk()
            ->assertSee('Scanner Melati')
            ->assertSee('Rp1.250.000')
            ->assertSee('hanya untuk memantau')
            ->assertDontSee('Serahkan &amp; catat alokasi', false);

        $this->actingAs($this->keuangan())
            ->get(route('kas.index'))
            ->assertOk()
            ->assertSee('Serahkan &amp; catat alokasi', false)
            ->assertSee('sudah dialokasikan');
    }

    public function test_kas_saya_hanya_menampilkan_kas_scanner_yang_login(): void
    {
        $rapat = $this->rapat();
        $sendiri = $this->scanner('Scanner Sendiri');
        $lain = $this->scanner('Scanner Lain');
        $keuangan = $this->keuangan();

        (new AlokasiKas)->serahkan($rapat, $sendiri, $keuangan, 750000);
        (new AlokasiKas)->serahkan($rapat, $lain, $keuangan, 2500000);

        $this->actingAs($sendiri)->get(route('kas-saya'))
            ->assertOk()
            ->assertSee('Rp750.000')
            ->assertDontSee('Rp2.500.000')
            ->assertDontSee('Scanner Lain');
    }

    public function test_kas_saya_memberi_petunjuk_bila_belum_dialokasikan(): void
    {
        $this->rapat();

        $this->actingAs($this->scanner())->get(route('kas-saya'))
            ->assertOk()
            ->assertSee('Kas awal belum dialokasikan Keuangan');
    }

    public function test_dashboard_keuangan_memakai_kas_nyata(): void
    {
        $rapat = $this->rapat();
        (new AlokasiKas)->serahkan($rapat, $this->scanner(), $this->keuangan(), 3000000);

        $this->actingAs($this->keuangan())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rp3.000.000')
            ->assertSee('Scanner Menerima Kas');
    }

    public function test_dashboard_scanner_memakai_kas_nyata(): void
    {
        $rapat = $this->rapat();
        $scanner = $this->scanner();

        (new AlokasiKas)->serahkan($rapat, $scanner, $this->keuangan(), 1750000);

        $this->actingAs($scanner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rp1.750.000')
            ->assertDontSee('Kas awal belum dialokasikan Keuangan');
    }

    public function test_keuangan_mengoreksi_nominal_dengan_jejak_audit(): void
    {
        $rapat = $this->rapat();
        $keuangan = $this->keuangan();
        $kas = (new AlokasiKas)->serahkan($rapat, $this->scanner(), $keuangan, 1000000);

        $this->actingAs($keuangan)
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 1500000, 'alasan' => 'Salah input nominal kas awal'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1500000.00', $kas->fresh()->kas_awal);

        $audit = LogAudit::query()->where('aksi', 'koreksi')->firstOrFail();

        $this->assertEquals(1000000, $audit->sebelum['kas_awal']);
        $this->assertEquals(1500000, $audit->sesudah['kas_awal']);
        $this->assertSame('Salah input nominal kas awal', $audit->alasan);
        $this->assertDatabaseCount('kas_scanner', 1);
    }

    public function test_koreksi_wajib_alasan_dan_nominal_berbeda(): void
    {
        $rapat = $this->rapat();
        $keuangan = $this->keuangan();
        $kas = (new AlokasiKas)->serahkan($rapat, $this->scanner(), $keuangan, 1000000);

        $this->actingAs($keuangan)
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 1200000, 'alasan' => 'salah'])
            ->assertSessionHasErrors('alasan');

        $this->actingAs($keuangan)
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 1000000, 'alasan' => 'Nominal dikirim ulang tanpa perubahan'])
            ->assertSessionHasErrors('kas');

        $this->assertSame('1000000.00', $kas->fresh()->kas_awal);
        $this->assertDatabaseCount('log_audit', 1);
    }

    public function test_koreksi_hanya_keuangan_dan_hanya_kas_aktif(): void
    {
        $rapat = $this->rapat();
        $keuangan = $this->keuangan();
        $kas = (new AlokasiKas)->serahkan($rapat, $this->scanner(), $keuangan, 1000000);

        $this->actingAs($this->scanner())
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 2000000, 'alasan' => 'Mencoba mengubah kas orang lain'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 2000000, 'alasan' => 'Mencoba mengubah kas sebagai admin'])
            ->assertForbidden();

        $kas->update(['status' => KasScanner::DITUTUP]);

        $this->actingAs($keuangan)
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 2000000, 'alasan' => 'Kas sudah ditutup tetapi dicoba diubah'])
            ->assertSessionHasErrors('kas');

        $this->assertSame('1000000.00', $kas->fresh()->kas_awal);
    }

    public function test_tombol_koreksi_hanya_untuk_keuangan(): void
    {
        $rapat = $this->rapat();
        $kas = (new AlokasiKas)->serahkan($rapat, $this->scanner('Scanner Melati'), $this->keuangan(), 1000000);

        $this->actingAs($this->keuangan())->get(route('kas.index'))
            ->assertOk()
            ->assertSeeHtml('data-bs-target="#koreksi-'.$kas->id.'"')
            ->assertSeeHtml('id="koreksi-'.$kas->id.'"');

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('kas.index'))
            ->assertOk()
            ->assertDontSeeHtml('id="koreksi-'.$kas->id.'"');
    }

    public function test_batas_peringatan_kas_bawaan_lima_kali_transport(): void
    {
        $rapat = $this->rapat(['transport_dasar' => 100000]);

        $this->assertSame(500000.0, $rapat->batasPeringatan());

        $rapat->update(['batas_peringatan_kas' => 250000]);

        $this->assertSame(250000.0, $rapat->fresh()->batasPeringatan());
    }

    public function test_kas_menipis_tampil_di_kas_saya_dan_halaman_kas(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI, 'transport_dasar' => 100000]);
        $scanner = $this->scanner();
        $kas = (new AlokasiKas)->serahkan($rapat, $scanner, $this->keuangan(), 300000);
        (new AlokasiKas)->terima($kas, $scanner);

        $this->actingAs($scanner);

        Livewire::test('presensi-scan')
            ->assertSee('Kas menipis')
            ->assertSee('Rp300.000');

        $this->actingAs(User::factory()->create(['role' => 'keuangan']))
            ->get(route('kas.index'))
            ->assertOk()
            ->assertSee('Kas menipis');
    }

    public function test_kas_cukup_tidak_menampilkan_peringatan(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI, 'transport_dasar' => 100000]);
        $scanner = $this->scanner();
        $kas = (new AlokasiKas)->serahkan($rapat, $scanner, $this->keuangan(), 500000);
        (new AlokasiKas)->terima($kas, $scanner);

        $this->actingAs($scanner);

        Livewire::test('presensi-scan')->assertDontSee('Kas menipis');
    }
}
