<?php

namespace Tests\Feature;

use App\Models\KasScanner;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-SCAN-04..06 / BR-006 / BR-008 / BR-009 / D-021 / D-022.
 */
class PresensiTest extends TestCase
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
        ]));

        (new PenyiapanPeserta)->siapkan($this->rapat);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scanner(): User
    {
        return User::factory()->create(['role' => 'scanner', 'name' => 'Scanner Satu']);
    }

    private function keuangan(): User
    {
        return User::factory()->create(['role' => 'keuangan', 'name' => 'Petugas Keuangan']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Admin RAT']);
    }

    /**
     * Verifikasi peserta pertama lalu kembalikan token QR mentahnya.
     */
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

    private function kas(User $scanner, float $nominal = 500000): KasScanner
    {
        $kas = (new AlokasiKas)->serahkan($this->rapat, $scanner, $this->keuangan(), $nominal);

        // D-026: kas harus dikonfirmasi Scanner sebelum bisa dipakai memindai.
        return (new AlokasiKas)->terima($kas, $scanner);
    }

    /**
     * Data URL PNG seperti yang dikirim canvas tanda tangan.
     */
    private function tte(): string
    {
        return 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('tte.png')->getContent());
    }

    /**
     * Scan lengkap dengan bukti foto + tanda tangan (D-023).
     */
    private function pindai(Rapat $rapat, string $token, User $scanner): array
    {
        return (new PresensiScan)->catat(
            $rapat,
            $token,
            $scanner,
            UploadedFile::fake()->image('foto.jpg'),
            $this->tte(),
        );
    }

    /**
     * Foto sebagai TemporaryUploadedFile seperti hasil unggah Livewire.
     */
    private function fotoTmp(): TemporaryUploadedFile
    {
        $isi = UploadedFile::fake()->image('sumber.jpg')->getContent();
        $path = 'livewire-tmp/foto-uji.jpg';

        Storage::disk('tmp-for-tests')->put($path, $isi);
        Storage::disk('tmp-for-tests')->put($path.'.json', json_encode([
            'name' => 'foto.jpg',
            'type' => 'image/jpeg',
            'size' => strlen($isi),
            'hash' => 'foto-uji.jpg',
        ]));

        return new TemporaryUploadedFile('foto-uji.jpg', 'tmp-for-tests');
    }

    public function test_scan_berhasil_mencatat_presensi_dan_mengurangi_kas(): void
    {
        $scanner = $this->scanner();
        $kas = $this->kas($scanner);
        $peserta = $this->peserta();
        $token = $this->token($peserta);

        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $this->assertSame(100000.0, $hasil['nominal']);

        $this->assertDatabaseHas('presensi', [
            'peserta_id' => $peserta->id,
            'nominal_transport_final' => 100000,
            'dicatat_oleh' => $scanner->id,
        ]);

        $this->assertDatabaseHas('transport_pembayaran', [
            'peserta_id' => $peserta->id,
            'kas_scanner_id' => $kas->id,
            'nominal' => 100000,
        ]);

        $this->assertSame(400000.0, $kas->fresh()->sisa());
        $this->assertDatabaseHas('log_audit', ['aksi' => 'presensi', 'entitas' => 'presensi']);
    }

    public function test_scan_ganda_ditolak(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $this->pindai($this->rapat, $token, $scanner);
        $kedua = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($kedua['ok']);
        $this->assertStringContainsString('sudah tercatat', $kedua['pesan']);
        $this->assertDatabaseCount('presensi', 1);
        $this->assertDatabaseCount('transport_pembayaran', 1);
    }

    public function test_scan_ditolak_bila_presensi_belum_dibuka(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum dibuka', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_scan_ditolak_bila_rapat_selesai(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());
        $this->rapat->update(['status' => Rapat::SELESAI]);

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('presensi ditutup', $hasil['pesan']);
    }

    public function test_scan_ditolak_bila_peserta_belum_verified(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $this->rapat->bukaPresensi($this->admin());

        $peserta = $this->peserta();
        $peserta->update(['qr_token_hash' => hash('sha256', 'token-pending')]);

        $hasil = $this->pindai($this->rapat, 'token-pending', $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('belum Verified', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_scan_ditolak_bila_token_tidak_dikenal(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, 'token-tidak-dikenal', $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('tidak dikenali', $hasil['pesan']);
    }

    public function test_scan_ditolak_bila_anggota_nonaktif(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $peserta->anggota->update(['status_anggota' => 'nonaktif']);
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Nonaktif', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_scan_ditolak_bila_kas_tidak_cukup(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner, 50000);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('tidak cukup', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
        $this->assertDatabaseCount('transport_pembayaran', 0);
    }

    public function test_scan_ditolak_bila_scanner_belum_menerima_kas(): void
    {
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $this->scanner());

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('Kas Anda belum dialokasikan', $hasil['pesan']);
    }

    public function test_token_rapat_lain_ditolak(): void
    {
        $lain = Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
            'judul' => 'Rapat Lain',
        ]));
        (new PenyiapanPeserta)->siapkan($lain);

        $tokenLain = $this->token($lain->peserta()->orderBy('id')->firstOrFail());

        $scanner = $this->scanner();
        $this->kas($scanner);
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $tokenLain, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('tidak dikenali', $hasil['pesan']);
    }

    public function test_transport_hangus_tetap_mencatat_presensi_tanpa_pembayaran(): void
    {
        $this->rapat->tier()->create(['menit_mulai' => 1, 'menit_akhir' => 60, 'potongan' => 100000, 'urutan' => 1]);

        $scanner = $this->scanner();
        $kas = $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        Carbon::setTestNow('2026-11-15 09:30:00');

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);
        $this->assertSame(0.0, $hasil['nominal']);
        $this->assertStringContainsString('hangus', $hasil['pesan']);
        $this->assertDatabaseHas('presensi', ['nominal_transport_final' => 0]);
        $this->assertDatabaseCount('transport_pembayaran', 0);
        $this->assertSame(500000.0, $kas->fresh()->sisa());
    }

    public function test_buka_presensi_hanya_admin_dan_hanya_sekali(): void
    {
        $this->actingAs($this->scanner())
            ->post(route('rapat.presensi', $this->rapat))
            ->assertForbidden();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('rapat.presensi', $this->rapat))
            ->assertSessionHasNoErrors();

        $rapat = $this->rapat->fresh();

        $this->assertNotNull($rapat->presensi_dibuka_at);
        $this->assertSame($admin->id, $rapat->dibuka_oleh);
        $this->assertSame(Rapat::VERIFIKASI, $rapat->status);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'buka_presensi', 'entitas' => 'rapat']);

        $this->actingAs($admin)
            ->post(route('rapat.presensi', $this->rapat))
            ->assertSessionHasErrors('rapat');
    }

    public function test_buka_presensi_ditolak_saat_rapat_draf(): void
    {
        $this->rapat->update(['status' => Rapat::DRAF]);

        $this->actingAs($this->admin())
            ->post(route('rapat.presensi', $this->rapat))
            ->assertSessionHasErrors('rapat');

        $this->assertNull($this->rapat->fresh()->presensi_dibuka_at);
    }

    public function test_scan_ditolak_bila_kas_ditutup(): void
    {
        $scanner = $this->scanner();
        $kas = $this->kas($scanner);
        $kas->update(['status' => KasScanner::DITUTUP]);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('sudah ditutup', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_zona_waktu_server_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', now()->timezoneName);
    }

    public function test_livewire_pemindai_menampilkan_hasil_scan(): void
    {
        $scanner = $this->scanner();
        $kas = $this->kas($scanner);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi($this->admin());

        $this->actingAs($scanner);

        // Pipeline unggah Livewire sendiri tidak diuji; komponen diuji dengan file tmp Livewire sungguhan.
        $komponen = Livewire::test('presensi-scan')->assertSee('Rp500.000');
        $komponen->instance()->token = $token;
        $komponen->instance()->foto = $this->fotoTmp();
        $komponen->instance()->tandaTangan = $this->tte();
        $komponen->instance()->scan();

        $this->assertTrue($komponen->instance()->berhasil);
        $this->assertSame('', $komponen->instance()->token);
        $this->assertStringContainsString($peserta->anggota->nama, $komponen->instance()->pesan);
        $this->assertNotNull($komponen->instance()->resiTerakhir);
        $this->assertSame(400000.0, $kas->fresh()->sisa());
        $this->assertDatabaseCount('presensi', 1);
    }

    public function test_kamera_qr_tersedia_dengan_fallback_manual(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $this->rapat->bukaPresensi($this->admin());

        $this->actingAs($scanner);

        Livewire::test('presensi-scan')
            ->assertSeeHtml('id="qrReader"')
            ->assertSeeHtml('id="tokenPresensi"');
    }

    public function test_halaman_pindai_memuat_pustaka_kamera(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $this->rapat->bukaPresensi($this->admin());

        $this->actingAs($scanner)
            ->get('/kas-saya')
            ->assertOk()
            ->assertSee('html5-qrcode.min.js', false);
    }

    public function test_livewire_menolak_scan_tanpa_foto_dan_tanda_tangan(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $this->actingAs($scanner);

        Livewire::test('presensi-scan')
            ->set('token', $token)
            ->call('scan')
            ->assertHasErrors(['foto', 'tandaTangan']);

        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_scan_ditolak_tanpa_foto_atau_tanda_tangan(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $hasil = (new PresensiScan)->catat($this->rapat, $token, $scanner, UploadedFile::fake()->image('foto.jpg'));

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('wajib diambil', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_tanda_tangan_bukan_png_ditolak(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $hasil = (new PresensiScan)->catat(
            $this->rapat,
            $token,
            $scanner,
            UploadedFile::fake()->image('foto.jpg'),
            'data:image/png;base64,'.base64_encode('bukan png'),
        );

        $this->assertFalse($hasil['ok']);
        $this->assertStringContainsString('gagal disimpan', $hasil['pesan']);
        $this->assertDatabaseCount('presensi', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_bukti_tersimpan_privat_dan_tidak_ikut_serialisasi(): void
    {
        $scanner = $this->scanner();
        $this->kas($scanner);
        $peserta = $this->peserta();
        $token = $this->token($peserta);
        $this->rapat->bukaPresensi($this->admin());

        $hasil = $this->pindai($this->rapat, $token, $scanner);

        $this->assertTrue($hasil['ok'], $hasil['pesan']);

        $presensi = Presensi::query()->where('peserta_id', $peserta->id)->firstOrFail();

        Storage::disk('local')->assertExists($presensi->foto_path);
        Storage::disk('local')->assertExists($presensi->tte_path);
        $this->assertSame("presensi/{$this->rapat->id}/{$peserta->id}/tte.png", $presensi->tte_path);

        $this->assertArrayNotHasKey('foto_path', $presensi->toArray());
        $this->assertArrayNotHasKey('tte_path', $presensi->toArray());
    }

    public function test_koreksi_kas_ditutup_setelah_ada_pembayaran(): void
    {
        $scanner = $this->scanner();
        $kas = $this->kas($scanner);
        $token = $this->token($this->peserta());
        $this->rapat->bukaPresensi($this->admin());

        $this->pindai($this->rapat, $token, $scanner);

        $this->actingAs($this->keuangan())
            ->post(route('kas.koreksi', $kas), ['kas_awal' => 900000, 'alasan' => 'Mencoba koreksi setelah kas dipakai'])
            ->assertSessionHasErrors('kas');

        $this->assertSame('500000.00', $kas->fresh()->kas_awal);
    }
}
