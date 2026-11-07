<?php

namespace Tests\Feature;

use App\Models\Rapat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
            'judul' => 'Rapat Anggota Tahunan 2026',
            'tanggal_acuan_saldo' => '2026-10-31',
            'tgl_rapat' => '2026-11-15',
            'jam_mulai_presensi' => '07:00',
            'jam_mulai_rat' => '08:00',
            'jam_selesai' => null,
            'lokasi' => 'Aula Koperasi Kredit Rukun',
            'alamat_lokasi' => 'Jl. Rukun No. 10, Kota Contoh',
            'catatan_undangan' => "Presensi dibuka pukul 07.00 WIB\nWajib membawa KTP/buku anggota",
            'tautan_google_maps' => 'https://maps.app.goo.gl/11FgFNfe5FVhPPmq6',
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
            'transport_dasar' => 100000,
            'toleransi_menit' => 60,
            'tier' => [
                ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 25000],
            ],
        ], $ubah);
    }

    public function test_admin_menyimpan_parameter_dan_tanggal_acuan_saldo(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'tanggal_acuan_saldo' => '2027-10-31',
                'tgl_rapat' => '2027-11-15',
                'transport_dasar' => 150000,
                'tier' => [
                    ['menit_mulai' => 1, 'menit_akhir' => 30, 'potongan' => 20000],
                    ['menit_mulai' => 31, 'menit_akhir' => 60, 'potongan' => 40000],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rapat->refresh();

        $this->assertSame('2027-10-31', $rapat->tanggal_acuan_saldo->toDateString());
        $this->assertSame(150000.0, (float) $rapat->transport_dasar);
        $this->assertSame('Jl. Rukun No. 10, Kota Contoh', $rapat->alamat_lokasi);
        $this->assertSame("Presensi dibuka pukul 07.00 WIB\nWajib membawa KTP/buku anggota", $rapat->catatan_undangan);
        $this->assertSame('https://maps.app.goo.gl/11FgFNfe5FVhPPmq6', $rapat->tautan_google_maps);
        $this->assertSame([1, 2], $rapat->tier()->pluck('urutan')->all());
        $this->assertSame(2, $rapat->tier()->count());
        $this->assertDatabaseHas('log_audit', ['entitas' => 'rapat', 'aksi' => 'ubah']);
    }

    public function test_tanggal_acuan_saldo_tidak_boleh_setelah_rapat(): void
    {
        $rapat = $this->rapat();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('rapat.update', $rapat), $this->payload(['tanggal_acuan_saldo' => '2026-11-16']))
            ->assertSessionHasErrors('tanggal_acuan_saldo');
    }

    public function test_alamat_lokasi_wajib_diisi(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload(['alamat_lokasi' => '']))
            ->assertSessionHasErrors('alamat_lokasi');

        $this->assertNull($rapat->fresh()->alamat_lokasi);
    }

    public function test_jam_dengan_detik_dinormalisasi_ke_format_jam_menit(): void
    {
        $rapat = $this->rapat();

        $this->actingAs($this->admin())
            ->put(route('rapat.update', $rapat), $this->payload([
                'jam_mulai_presensi' => '07:00:00',
                'jam_mulai_rat' => '08:00:00',
                'jam_selesai' => '12:30:00',
            ]))
            ->assertSessionHasNoErrors();

        $rapat->refresh();

        $this->assertSame('07:00', $rapat->jam_mulai_presensi);
        $this->assertSame('08:00', $rapat->jam_mulai_rat);
        $this->assertSame('12:30', $rapat->jam_selesai);
    }

    public function test_tautan_google_maps_opsional_dan_menolak_domain_lain(): void
    {
        $rapat = $this->rapat();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('rapat.update', $rapat), $this->payload(['tautan_google_maps' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($rapat->fresh()->tautan_google_maps);

        $this->actingAs($admin)
            ->put(route('rapat.update', $rapat), $this->payload(['tautan_google_maps' => 'https://contoh.test/lokasi']))
            ->assertSessionHasErrors('tautan_google_maps');

        $this->assertNull($rapat->fresh()->tautan_google_maps);
    }

    public function test_jadwal_dan_alamat_tampil_di_informasi_umum(): void
    {
        $rapat = $this->rapat(['alamat_lokasi' => 'Jl. Rukun No. 10, Kota Contoh']);

        $this->actingAs($this->admin())
            ->get(route('rapat.index'))
            ->assertOk()
            ->assertDontSee('Tahun Acuan')
            ->assertSee('Tanggal Acuan Saldo')
            ->assertSeeInOrder([
                'Informasi Umum Rapat',
                'Jam Mulai Presensi',
                'Jam Mulai Acara',
                'Jam Selesai',
                'Nama Lokasi Rapat',
                'Tautan Titik Lokasi Google Maps',
                'Alamat Lengkap Lokasi Rapat',
                'Kriteria Kelayakan Anggota',
            ]);
    }

    public function test_mode_a_menampilkan_kolektibilitas_dan_menyembunyikan_switch_pinjaman(): void
    {
        $this->rapat(['mode_kelayakan' => 'A']);

        $this->actingAs($this->admin())
            ->get(route('rapat.index'))
            ->assertOk()
            ->assertSee('class="col-md-4 " id="grup-kolektibilitas"', false)
            ->assertSee('class="col-md-8 d-none" id="grup-pinjaman"', false)
            ->assertSee('form-check form-switch', false);
    }

    public function test_mode_b_menyembunyikan_kolektibilitas_dan_mengaktifkan_switch_pinjaman(): void
    {
        $this->rapat(['mode_kelayakan' => 'B', 'wajib_pinjaman_lancar' => false]);

        $this->actingAs($this->admin())
            ->get(route('rapat.index'))
            ->assertOk()
            ->assertSee('class="col-md-4 d-none" id="grup-kolektibilitas"', false)
            ->assertSee('class="col-md-8 " id="grup-pinjaman"', false)
            ->assertSeeInOrder(['id="wajib_pinjaman_lancar"', 'checked'], false);
    }

    public function test_rapat_hanya_memakai_tanggal_acuan_dan_tahun_buku_shu_tetap_ada(): void
    {
        $this->assertFalse(Schema::hasColumn('rapat', 'tahun_acuan'));
        $this->assertFalse(Schema::hasColumn('rapat', 'tahun_buku'));
        $this->assertTrue(Schema::hasColumn('shu', 'tahun_buku'));
        $this->assertTrue(Schema::hasColumn('shu_publik', 'tahun_buku'));
        $this->assertTrue(Schema::hasColumn('log_cek_shu', 'tahun_buku'));
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
