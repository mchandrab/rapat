<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\Kuorum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BR-010: hanya anggota jenis `biasa` yang dihitung; target =
 * floor(persen × basis populasi) + tambahan dari konfigurasi rapat.
 */
class KuorumTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::VERIFIKASI,
            'tgl_rapat' => '2026-11-15',
            'persen_kuorum' => 50,
            'tambahan_kuorum' => 1,
        ], $ubah));
    }

    private function anggota(int $urut, string $jenis = 'biasa', string $status = 'aktif'): Anggota
    {
        return Anggota::create([
            'no_anggota' => str_pad((string) $urut, 15, '0', STR_PAD_LEFT),
            'nama' => 'Anggota '.$urut,
            'jenis_anggota' => $jenis,
            'status_anggota' => $status,
            'tgl_ja' => '2020-01-01',
            'simpanan_wajib' => 0,
        ]);
    }

    private function peserta(Rapat $rapat, Anggota $anggota, string $status = Peserta::PENDING): Peserta
    {
        return Peserta::create([
            'rapat_id' => $rapat->id,
            'anggota_id' => $anggota->id,
            'hasil_kelayakan' => Peserta::LAYAK,
            'status_verifikasi' => $status,
            'snapshot_konfigurasi' => [],
        ]);
    }

    private function hadir(Peserta $peserta, string $jam): void
    {
        Presensi::create([
            'peserta_id' => $peserta->id,
            'datang_at' => $jam,
            'nominal_transport_final' => 0,
        ]);
    }

    public function test_target_memakai_rumus_floor_persen_ditambah_tambahan(): void
    {
        $rapat = $this->rapat();

        foreach (range(1, 101) as $urut) {
            $this->peserta($rapat, $this->anggota($urut));
        }

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(101, $hasil['populasi']);
        $this->assertSame(51, $hasil['target']);
    }

    public function test_populasi_genap_menghasilkan_target_separuh_ditambah_satu(): void
    {
        $rapat = $this->rapat();

        foreach (range(1, 100) as $urut) {
            $this->peserta($rapat, $this->anggota($urut));
        }

        $this->assertSame(51, (new Kuorum)->hitung($rapat)['target']);
    }

    public function test_hanya_anggota_biasa_dan_aktif_masuk_populasi(): void
    {
        $rapat = $this->rapat();

        foreach ([1, 2, 3] as $urut) {
            $this->peserta($rapat, $this->anggota($urut));
        }

        $this->peserta($rapat, $this->anggota(4, 'luar_biasa'));
        $this->peserta($rapat, $this->anggota(5, 'biasa', 'nonaktif'));

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(3, $hasil['populasi']);
        $this->assertSame(2, $hasil['target']);
    }

    public function test_kehadiran_luar_biasa_tidak_menambah_kuorum(): void
    {
        $rapat = $this->rapat();

        $biasa = $this->peserta($rapat, $this->anggota(1));
        $luarBiasa = $this->peserta($rapat, $this->anggota(2, 'luar_biasa'));

        $this->hadir($luarBiasa, '2026-11-15 07:10:00');

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(0, $hasil['hadir']);
        $this->assertSame(1, $hasil['hadir_luar_biasa']);
        $this->assertFalse($hasil['tercapai']);

        $this->hadir($biasa, '2026-11-15 07:20:00');

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(1, $hasil['hadir']);
        $this->assertTrue($hasil['tercapai']);
    }

    public function test_status_sah_tepat_saat_target_tercapai(): void
    {
        $rapat = $this->rapat();

        $peserta = [];

        foreach ([1, 2, 3] as $urut) {
            $peserta[$urut] = $this->peserta($rapat, $this->anggota($urut));
        }

        // Populasi 3, target floor(50% × 3) + 1 = 2.
        $this->hadir($peserta[1], '2026-11-15 07:00:00');

        $hasil = (new Kuorum)->hitung($rapat);
        $this->assertFalse($hasil['tercapai']);
        $this->assertSame(1, $hasil['kekurangan']);
        $this->assertNull($hasil['jam_tercapai']);

        $this->hadir($peserta[2], '2026-11-15 07:15:00');

        $hasil = (new Kuorum)->hitung($rapat);
        $this->assertTrue($hasil['tercapai']);
        $this->assertSame(0, $hasil['kekurangan']);
        $this->assertSame('2026-11-15 07:15:00', $hasil['jam_tercapai']->format('Y-m-d H:i:s'));

        $this->hadir($peserta[3], '2026-11-15 07:30:00');

        $hasil = (new Kuorum)->hitung($rapat);
        $this->assertSame(3, $hasil['hadir']);
        $this->assertSame(100.0, $hasil['persen']);
        $this->assertSame('2026-11-15 07:15:00', $hasil['jam_tercapai']->format('Y-m-d H:i:s'));
    }

    public function test_basis_terverifikasi_hanya_menghitung_peserta_verified(): void
    {
        $rapat = $this->rapat(['basis_kuorum' => 'terverifikasi']);

        $this->peserta($rapat, $this->anggota(1), Peserta::VERIFIED);
        $this->peserta($rapat, $this->anggota(2));
        $this->peserta($rapat, $this->anggota(3));

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(1, $hasil['populasi']);
        $this->assertSame(1, $hasil['target']);
    }

    public function test_presensi_rapat_lain_tidak_menambah_kuorum(): void
    {
        $rapat = $this->rapat();
        $lain = $this->rapat(['tgl_rapat' => '2026-12-20']);

        $this->peserta($rapat, $this->anggota(1));
        $this->hadir($this->peserta($lain, $this->anggota(2)), '2026-12-20 08:00:00');

        $hasil = (new Kuorum)->hitung($rapat);

        $this->assertSame(1, $hasil['populasi']);
        $this->assertSame(0, $hasil['hadir']);
    }

    public function test_halaman_kuorum_hanya_untuk_admin_dan_keuangan(): void
    {
        $this->rapat();

        foreach (['admin', 'keuangan'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('kuorum.index'))
                ->assertOk()
                ->assertSee('Kuorum');
        }

        foreach (['helpdesk', 'scanner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('kuorum.index'))
                ->assertForbidden();
        }
    }

    public function test_papan_kuorum_menampilkan_angka_dan_status(): void
    {
        $rapat = $this->rapat();

        foreach ([1, 2, 3] as $urut) {
            $peserta = $this->peserta($rapat, $this->anggota($urut));

            if ($urut <= 2) {
                $this->hadir($peserta, '2026-11-15 07:0'.$urut.':00');
            }
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('kuorum-papan')
            ->assertSee('Kuorum tercapai — rapat sah')
            ->assertSee('floor(50% × 3) + 1')
            ->assertDontSee('Kuorum belum tercapai');
    }

    public function test_papan_kuorum_belum_tercapai_dan_belum_ada_rapat(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('kuorum-papan')->assertSee('Belum ada rapat');

        $this->rapat();

        Livewire::test('kuorum-papan')
            ->assertSee('Kuorum belum tercapai')
            ->assertSee('Peserta belum disiapkan');
    }

    public function test_papan_kuorum_publik_dapat_dibuka_tanpa_login_dan_hanya_menampilkan_agregat(): void
    {
        $rapat = $this->rapat(['judul' => 'RAT Publik 2026']);
        $anggota = $this->anggota(123456789, 'biasa');
        $anggota->update(['nama' => 'Nama Sangat Rahasia']);
        $peserta = $this->peserta($rapat, $anggota, Peserta::VERIFIED);
        $this->hadir($peserta, '2026-11-15 07:10:00');

        $this->get(route('kuorum.public'))
            ->assertOk()
            ->assertSee('Papan Kuorum Publik')
            ->assertSee('RAT Publik 2026')
            ->assertSee('Kuorum tercapai — rapat sah')
            ->assertSeeInOrder(['Hadir', '1', 'Target', '1', 'Populasi', '1', 'Persentase', '100%'])
            ->assertDontSee('Nama Sangat Rahasia')
            ->assertDontSee($anggota->no_anggota)
            ->assertDontSee('nominal_transport_final');

        $this->assertGuest();
    }

    public function test_papan_kuorum_publik_menampilkan_keadaan_kosong(): void
    {
        $this->get(route('kuorum.public'))
            ->assertOk()
            ->assertSee('Belum ada rapat yang dapat ditampilkan.');
    }

    public function test_ringkasan_kuorum_hanya_ditampilkan_di_dashboard_scanner(): void
    {
        $this->rapat(['judul' => 'Rapat Scanner']);

        $scanner = User::factory()->create(['role' => 'scanner']);
        $this->actingAs($scanner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan Kuorum')
            ->assertSee('Rapat Scanner');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Ringkasan Kuorum');
    }
}
