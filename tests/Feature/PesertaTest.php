<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Models\User;
use App\Services\PenyiapanPeserta;
use App\Services\UndanganPeserta;
use App\Services\VerifikasiPeserta;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AnggotaSeeder::class);
    }

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), [
            'tanggal_acuan_saldo' => '2026-12-31',
        ], $ubah));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(string $nba): Anggota
    {
        return Anggota::query()->where('no_anggota', $nba)->firstOrFail();
    }

    public function test_siapkan_membuat_peserta_dengan_snapshot(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);

        $this->actingAs($this->admin())
            ->post(route('peserta.siapkan', $rapat))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('peserta', 6);

        $peserta = Peserta::query()->where('anggota_id', $this->anggota('020006000010341')->id)->firstOrFail();

        $this->assertSame(Peserta::TIDAK_LAYAK, $peserta->hasil_kelayakan);
        $this->assertSame(Peserta::PENDING, $peserta->status_verifikasi);
        $this->assertSame('300000.00', $peserta->snapshot_minimum_proporsional);
        $this->assertSame('A', $peserta->snapshot_konfigurasi['mode_kelayakan']);
        $this->assertSame('2026-12-31', $peserta->snapshot_konfigurasi['tanggal_acuan_saldo']);
        $this->assertArrayNotHasKey('tahun_acuan', $peserta->snapshot_konfigurasi);

        $this->assertDatabaseHas('log_audit', ['aksi' => 'siapkan', 'entitas' => 'peserta']);
    }

    public function test_siapkan_idempoten_dan_tidak_menimpa_keputusan(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $peserta = Peserta::query()->where('anggota_id', $this->anggota('020006000010343')->id)->firstOrFail();
        (new VerifikasiPeserta)->verifikasi($peserta, $admin);

        $this->actingAs($admin)->post(route('peserta.siapkan', $rapat))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('peserta', 6);
        $this->assertSame(Peserta::VERIFIED, $peserta->fresh()->status_verifikasi);
    }

    public function test_siapkan_ulang_memperbarui_snapshot_peserta_pending_saja(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $pending = Peserta::query()->where('anggota_id', $this->anggota('020006000010340')->id)->firstOrFail();
        $diputuskan = Peserta::query()->where('anggota_id', $this->anggota('020006000010343')->id)->firstOrFail();
        (new VerifikasiPeserta)->verifikasi($diputuskan, $admin);

        $rapat->update(['tanggal_acuan_saldo' => '2026-10-31']);
        $hasil = (new PenyiapanPeserta)->siapkan($rapat->fresh());

        $this->assertSame(5, $hasil['diperbarui']);
        $this->assertSame(1, $hasil['dilewati']);
        $this->assertSame('250000.00', $pending->fresh()->snapshot_minimum_proporsional);
        $this->assertSame('75000.00', $diputuskan->fresh()->snapshot_minimum_proporsional);
        $this->assertSame(Peserta::VERIFIED, $diputuskan->fresh()->status_verifikasi);
    }

    public function test_siapkan_hanya_untuk_admin(): void
    {
        $rapat = $this->rapat();

        $this->actingAs(User::factory()->create(['role' => 'helpdesk']))
            ->post(route('peserta.siapkan', $rapat))
            ->assertForbidden();

        $this->assertDatabaseCount('peserta', 0);
    }

    public function test_siapkan_ditolak_bila_rapat_terkunci(): void
    {
        $rapat = $this->rapat(['status' => Rapat::BERLANGSUNG, 'locked_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('peserta.siapkan', $rapat))
            ->assertSessionHasErrors('peserta');

        $this->assertDatabaseCount('peserta', 0);
    }

    public function test_token_qr_unik_terenkripsi_dan_tidak_berubah_saat_diverifikasi_ulang(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $budi = Peserta::query()->where('anggota_id', $this->anggota('020006000010339')->id)->firstOrFail();
        $siti = Peserta::query()->where('anggota_id', $this->anggota('020006000010340')->id)->firstOrFail();

        $layanan = new VerifikasiPeserta;
        $layanan->verifikasi($budi, $admin);
        $layanan->verifikasi($siti, $admin);

        $budi->refresh();
        $siti->refresh();

        $this->assertNotSame($budi->qr_token_hash, $siti->qr_token_hash);
        $this->assertSame(64, strlen($budi->qr_token_hash));
        $this->assertNotSame($this->anggota('020006000010339')->no_anggota, $budi->qr_token_hash);
        $this->assertNotNull($budi->qr_dibuat_at);

        $token = $layanan->token($budi);
        $this->assertSame($budi->qr_token_hash, hash('sha256', $token));

        $layanan->verifikasi($budi, $admin);
        $this->assertSame($budi->qr_token_hash, $budi->fresh()->qr_token_hash);
    }

    public function test_verifikasi_dan_penolakan_dicatat_di_log_audit(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $budi = Peserta::query()->where('anggota_id', $this->anggota('020006000010339')->id)->firstOrFail();
        $andi = Peserta::query()->where('anggota_id', $this->anggota('020006000010341')->id)->firstOrFail();

        (new VerifikasiPeserta)->verifikasi($budi, $admin);
        (new VerifikasiPeserta)->tolak($andi, $admin, 'Tidak memenuhi syarat');

        $this->assertDatabaseHas('log_audit', ['aksi' => 'verifikasi', 'entitas' => 'peserta', 'entitas_id' => $budi->id]);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'tolak', 'entitas' => 'peserta', 'entitas_id' => $andi->id]);
        $this->assertSame('Tidak memenuhi syarat', $andi->fresh()->alasan_penolakan);
    }

    public function test_mode_b_memakai_kriteria_pinjaman_lancar(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI, 'mode_kelayakan' => 'B']);

        (new PenyiapanPeserta)->siapkan($rapat);

        $andi = Peserta::query()->where('anggota_id', $this->anggota('020006000010341')->id)->firstOrFail();
        $hendra = Peserta::query()->where('anggota_id', $this->anggota('020006000010344')->id)->firstOrFail();

        $this->assertSame(Peserta::TIDAK_LAYAK, $andi->hasil_kelayakan);
        $this->assertSame(Peserta::LAYAK, $hendra->hasil_kelayakan);
    }

    public function test_halaman_rapat_dan_dashboard_menampilkan_data_peserta_nyata(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $budi = Peserta::query()->where('anggota_id', $this->anggota('020006000010339')->id)->firstOrFail();
        (new VerifikasiPeserta)->verifikasi($budi, $admin);

        $this->actingAs($admin)->get(route('rapat.index'))
            ->assertOk()
            ->assertSee('Siapkan Peserta')
            ->assertSee('id="modalSiapkanPeserta"', false)
            ->assertSee('Peserta sedang disiapkan')
            ->assertSee('Peserta Pending akan dihitung ulang')
            ->assertSee("form.addEventListener('submit'", false)
            ->assertSee('peserta 6 dari 6 anggota');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 / 6');
    }

    public function test_riwayat_undangan_membaca_log_nyata(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);
        $admin = $this->admin();

        (new PenyiapanPeserta)->siapkan($rapat);

        $budi = Peserta::query()->where('anggota_id', $this->anggota('020006000010339')->id)->firstOrFail();
        (new VerifikasiPeserta)->verifikasi($budi, $admin);
        (new UndanganPeserta)->kirim($budi->fresh(), $admin, 'cetak');

        $this->actingAs($admin)->get(route('undangan.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Cetak')
            ->assertSee('1 catatan')
            ->assertDontSee('Belum ada undangan dikirim atau dicetak.');
    }

    public function test_anggota_nonaktif_tetap_dinilai_kelayakannya(): void
    {
        $rapat = $this->rapat(['status' => Rapat::VERIFIKASI]);

        (new PenyiapanPeserta)->siapkan($rapat);

        $rina = Peserta::query()->where('anggota_id', $this->anggota('020006000010342')->id)->firstOrFail();

        $this->assertSame(Peserta::LAYAK, $rina->hasil_kelayakan);
        $this->assertSame('nonaktif', $this->anggota('020006000010342')->status_anggota);
    }
}
