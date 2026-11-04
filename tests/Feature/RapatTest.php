<?php

namespace Tests\Feature;

use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RapatTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), $ubah));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $ubah = []): array
    {
        return array_merge([
            'jenis' => 'rat',
            'judul' => 'Rapat Anggota Tahunan Tahun Buku 2026',
            'tahun_buku' => 2026,
            'tgl_rapat' => '2026-11-15',
            'jam_mulai_presensi' => '07:00',
            'jam_mulai_rat' => '08:00',
            'jam_selesai' => null,
            'lokasi' => 'Aula Koperasi Kredit Rukun',
            'mode_presensi' => '1x',
            'mode_kelayakan' => 'A',
            'minimal_simpanan_tahunan' => 300000,
            'simpanan_per_bulan' => 25000,
            'gunakan_proporsional' => 1,
            'batas_kolektibilitas' => 2,
            'wajib_pinjaman_lancar' => 0,
            'basis_kuorum' => 'semua_biasa',
            'persen_kuorum' => 50,
            'tambahan_kuorum' => 1,
            'jumlah_pemenang_doorprize' => 10,
            'transport_dasar' => 100000,
            'toleransi_menit' => 60,
            'tier' => [
                ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 25000],
            ],
        ], $ubah);
    }

    public function test_admin_menyimpan_parameter_dan_acuan_saldo_dihitung(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'tahun_buku' => 2027,
                'transport_dasar' => 150000,
                'tier' => [
                    ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 20000],
                    ['menit_mulai' => 31, 'menit_akhir' => 60, 'potongan' => 40000],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rapat->refresh();

        $this->assertSame(2027, $rapat->tahun_buku);
        $this->assertSame('2027-12-31', $rapat->tanggal_acuan_saldo->toDateString());
        $this->assertSame(150000.0, (float) $rapat->transport_dasar);
        $this->assertSame([1, 2], $rapat->tier()->pluck('urutan')->all());
        $this->assertSame(2, $rapat->tier()->count());
        $this->assertDatabaseHas('log_audit', ['entitas' => 'rapat', 'aksi' => 'ubah']);
    }

    public function test_helpdesk_tidak_boleh_mengubah_parameter(): void
    {
        $rapat = $this->rapat();

        $this->actingAs(User::factory()->create(['role' => 'helpdesk']))
            ->put(route('rapat.update', $rapat), $this->payload(['transport_dasar' => 999]))
            ->assertForbidden();

        $this->assertSame(100000.0, (float) $rapat->fresh()->transport_dasar);
    }

    public function test_mode_presensi_2x_dapat_dipilih(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload(['mode_presensi' => '2x']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2x', $rapat->fresh()->mode_presensi);
    }

    public function test_parameter_terkunci_saat_berlangsung(): void
    {
        $rapat = $this->rapat(['status' => Rapat::BERLANGSUNG, 'locked_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload(['transport_dasar' => 999]))
            ->assertSessionHasErrors('rapat');

        $this->assertSame(100000.0, (float) $rapat->fresh()->transport_dasar);
    }

    public function test_transisi_status_mengikuti_urutan_dan_mengunci_parameter(): void
    {
        $rapat = $this->rapat();
        $admin = $this->admin();

        // Draf tidak boleh langsung Selesai.
        $this->actingAs($admin)
            ->post(route('rapat.transisi', $rapat), ['aksi' => 'selesai'])
            ->assertSessionHasErrors('rapat');

        $this->actingAs($admin)->post(route('rapat.transisi', $rapat), ['aksi' => 'verifikasi'])->assertSessionHasNoErrors();
        $this->assertSame(Rapat::VERIFIKASI, $rapat->fresh()->status);

        $this->actingAs($admin)->post(route('rapat.transisi', $rapat), ['aksi' => 'mulai'])->assertSessionHasNoErrors();

        $rapat->refresh();
        $this->assertSame(Rapat::BERLANGSUNG, $rapat->status);
        $this->assertNotNull($rapat->locked_at);
        $this->assertTrue($rapat->terkunci());
        $this->assertDatabaseHas('log_audit', ['entitas' => 'rapat', 'aksi' => 'ubah_status']);

        $this->actingAs($admin)->post(route('rapat.transisi', $rapat), ['aksi' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame(Rapat::SELESAI, $rapat->fresh()->status);
    }

    public function test_tier_tumpang_tindih_ditolak(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'tier' => [
                    ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 25000],
                    ['menit_mulai' => 30, 'menit_akhir' => 60, 'potongan' => 50000],
                ],
            ]))
            ->assertSessionHasErrors('tier');

        $this->assertSame(0, $rapat->tier()->count());
    }

    public function test_tier_bolong_ditolak(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'tier' => [
                    ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 25000],
                    ['menit_mulai' => 32, 'menit_akhir' => 60, 'potongan' => 50000],
                ],
            ]))
            ->assertSessionHasErrors('tier');

        $this->assertSame(0, $rapat->tier()->count());
    }

    public function test_tier_harus_mulai_menit_satu(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'tier' => [
                    ['menit_mulai' => 2, 'menit_akhir' => 30, 'potongan' => 25000],
                ],
            ]))
            ->assertSessionHasErrors('tier');

        $this->assertSame(0, $rapat->tier()->count());
    }

    public function test_halaman_rat_menampilkan_parameter_tersimpan(): void
    {
        $rapat = $this->rapat(['transport_dasar' => 125000]);

        $this->actingAs($this->admin())
            ->get(route('rapat.index'))
            ->assertOk()
            ->assertSee('value="125000"', false)
            ->assertSee('Simpan Pengaturan')
            ->assertDontSee('name="aksi" value="mulai"', false);
    }

    public function test_halaman_rat_membuat_parameter_awal_bila_belum_ada(): void
    {
        $this->assertSame(0, Rapat::query()->count());

        $this->actingAs($this->admin())
            ->get(route('rapat.index'))
            ->assertOk();

        $this->assertSame(1, Rapat::query()->count());
        $this->assertSame(Rapat::DRAF, Rapat::query()->first()->status);
    }
}
