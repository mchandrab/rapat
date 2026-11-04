<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Models\User;
use App\Services\PenyiapanPeserta;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerifikasiTest extends TestCase
{
    use RefreshDatabase;

    private Rapat $rapat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AnggotaSeeder::class);

        $this->rapat = Rapat::create(array_merge(Rapat::bawaan(), ['status' => Rapat::VERIFIKASI]));
        (new PenyiapanPeserta)->siapkan($this->rapat);
    }

    private function helpdesk(): User
    {
        return User::factory()->create(['role' => 'helpdesk', 'name' => 'Ani Helpdesk']);
    }

    private function anggota(string $nba): Anggota
    {
        return Anggota::query()->where('no_anggota', $nba)->firstOrFail();
    }

    public function test_kelola_anggota_menampilkan_informasi_dulu_bukan_form(): void
    {
        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->call('kelola', $this->anggota('020006000010339')->id)
            ->assertSet('modeEdit', false)
            ->assertSee('Minimum proporsional')
            ->assertSee('Layak')
            ->assertDontSee('Edit Data')
            ->assertDontSee('Simpan Perubahan');
    }

    public function test_helpdesk_memverifikasi_dan_status_tersimpan(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010343');

        Livewire::test('peserta-verifikasi')
            ->call('verifikasi', $anggota->id)
            ->assertSee('Verified');

        $this->assertDatabaseHas('peserta', [
            'rapat_id' => $this->rapat->id,
            'anggota_id' => $anggota->id,
            'status_verifikasi' => Peserta::VERIFIED,
        ]);
    }

    public function test_tidak_layak_tanpa_alasan_ditolak(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010341');

        Livewire::test('peserta-verifikasi')
            ->call('verifikasi', $anggota->id)
            ->assertSee('hanya boleh Verified dengan alasan tertulis');

        $this->assertDatabaseHas('peserta', [
            'anggota_id' => $anggota->id,
            'status_verifikasi' => Peserta::PENDING,
        ]);
    }

    public function test_tidak_layak_dengan_alasan_bisa_diverifikasi(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010341');

        Livewire::test('peserta-verifikasi')
            ->set('alasan', 'Disetujui pengurus karena kondisi khusus')
            ->call('verifikasi', $anggota->id);

        $this->assertDatabaseHas('peserta', [
            'anggota_id' => $anggota->id,
            'status_verifikasi' => Peserta::VERIFIED,
            'alasan_override' => 'Disetujui pengurus karena kondisi khusus',
        ]);
    }

    public function test_tolak_menyimpan_status_dan_alasan(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010341');

        Livewire::test('peserta-verifikasi')
            ->set('alasan', 'Kolektibilitas melebihi batas rapat')
            ->call('tolak', $anggota->id);

        $this->assertDatabaseHas('peserta', [
            'anggota_id' => $anggota->id,
            'status_verifikasi' => Peserta::REJECTED,
            'alasan_penolakan' => 'Kolektibilitas melebihi batas rapat',
        ]);
    }

    public function test_undangan_cetak_dicatat_di_riwayat(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010343');

        Livewire::test('peserta-verifikasi')
            ->call('verifikasi', $anggota->id)
            ->call('cetakUndangan', $anggota->id);

        $this->assertDatabaseHas('log_undangan', [
            'rapat_id' => $this->rapat->id,
            'metode' => 'cetak',
            'status' => 'dicetak',
        ]);
    }

    public function test_whatsapp_gagal_tertutup_sampai_integrasi_aktif(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010343');

        Livewire::test('peserta-verifikasi')
            ->call('verifikasi', $anggota->id)
            ->call('kirimWhatsApp', $anggota->id)
            ->assertSee('Integrasi Fonnte belum aktif');

        $this->assertDatabaseHas('log_undangan', [
            'rapat_id' => $this->rapat->id,
            'metode' => 'wa',
            'status' => 'gagal',
        ]);
    }

    public function test_undangan_ditolak_sebelum_verified(): void
    {
        $this->actingAs($this->helpdesk());

        $anggota = $this->anggota('020006000010343');

        Livewire::test('peserta-verifikasi')
            ->call('cetakUndangan', $anggota->id)
            ->assertSee('setelah peserta Verified');

        $this->assertDatabaseCount('log_undangan', 0);
    }

    public function test_verifikasi_ditolak_setelah_rapat_terkunci(): void
    {
        $this->actingAs($this->helpdesk());

        $this->rapat->update(['status' => Rapat::BERLANGSUNG, 'locked_at' => now()]);

        $anggota = $this->anggota('020006000010343');

        Livewire::test('peserta-verifikasi')
            ->call('verifikasi', $anggota->id)
            ->assertSee('Rapat sudah dikunci');

        $this->assertDatabaseHas('peserta', [
            'anggota_id' => $anggota->id,
            'status_verifikasi' => Peserta::PENDING,
        ]);
    }

    public function test_filter_kelayakan_menyaring_daftar(): void
    {
        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->set('filterKelayakan', 'tidak_layak')
            ->assertSee('Andi Pratama')
            ->assertDontSee('Siti Rahma');
    }

    public function test_admin_membuka_form_lewat_tombol_edit_dan_bisa_membatalkan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('peserta-verifikasi')
            ->call('kelola', $this->anggota('020006000010339')->id)
            ->assertSee('Edit Data')
            ->call('edit')
            ->assertSet('modeEdit', true)
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('Minimum proporsional')
            ->call('batalEdit')
            ->assertSet('modeEdit', false)
            ->assertSee('Minimum proporsional');
    }

    public function test_tombol_aksi_memiliki_indikator_loading_terpisah(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('peserta-verifikasi')
            ->call('kelola', $this->anggota('020006000010339')->id)
            ->assertSee('Memverifikasi...')
            ->assertSee('Menolak...')
            ->assertSee('Mengirim...')
            ->assertSee('Menyiapkan...')
            ->assertSee('Membuka...')
            ->assertSeeHtml('wire:target="kirimWhatsApp"')
            ->assertSeeHtml('wire:target="cetakUndangan"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }

    public function test_helpdesk_tidak_boleh_membuka_form_edit(): void
    {
        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->call('kelola', $this->anggota('020006000010339')->id)
            ->call('edit')
            ->assertForbidden();
    }

    public function test_pencarian_menyaring_daftar(): void
    {
        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->set('cari', '020006.000.010.340')
            ->assertSee('Siti Rahma')
            ->assertDontSee('Budi Santoso');
    }

    public function test_daftar_memakai_paginasi_bootstrap_dengan_label_indonesia(): void
    {
        Anggota::factory()->count(20)->create();

        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->assertSee('Sebelumnya')
            ->assertSeeHtml('class="pagination"');
    }

    public function test_peserta_belum_disiapkan_memberi_petunjuk(): void
    {
        Peserta::query()->delete();

        $this->actingAs($this->helpdesk());

        Livewire::test('peserta-verifikasi')
            ->assertSee('Peserta rapat belum disiapkan');
    }
}
