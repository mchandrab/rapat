<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\DoorprizePemenang;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\Doorprize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BR-011: undian doorprize hanya untuk peserta hadir, satu hasil per peserta
 * per rapat, dan peserta yang sudah keluar tidak diundi lagi.
 */
class DoorprizeTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::BERLANGSUNG,
            'tgl_rapat' => '2026-11-15',
            'jumlah_pemenang_doorprize' => 2,
        ], $ubah));
    }

    private function peserta(Rapat $rapat, int $urut, bool $hadir = true): Peserta
    {
        $anggota = Anggota::create([
            'no_anggota' => str_pad((string) $urut, 15, '0', STR_PAD_LEFT),
            'nama' => 'Anggota '.$urut,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
            'simpanan_wajib' => 0,
        ]);

        $peserta = Peserta::create([
            'rapat_id' => $rapat->id,
            'anggota_id' => $anggota->id,
            'hasil_kelayakan' => Peserta::LAYAK,
            'status_verifikasi' => Peserta::VERIFIED,
            'snapshot_konfigurasi' => [],
        ]);

        if ($hadir) {
            Presensi::create([
                'peserta_id' => $peserta->id,
                'datang_at' => '2026-11-15 07:30:00',
                'nominal_transport_final' => 0,
            ]);
        }

        return $peserta;
    }

    public function test_hanya_peserta_hadir_yang_bisa_diundi(): void
    {
        $rapat = $this->rapat();
        $hadir = $this->peserta($rapat, 1);
        $this->peserta($rapat, 2, hadir: false);

        $kandidat = (new Doorprize)->kandidat($rapat)->pluck('id')->all();

        $this->assertSame([$hadir->id], $kandidat);
    }

    public function test_peserta_yang_sudah_menang_atau_gugur_tidak_diundi_lagi(): void
    {
        $rapat = $this->rapat();
        $menang = $this->peserta($rapat, 1);
        $gugur = $this->peserta($rapat, 2);
        $tersisa = $this->peserta($rapat, 3);

        $layanan = new Doorprize;
        $layanan->putuskan($rapat, $menang, true);
        $layanan->putuskan($rapat, $gugur, false);

        $this->assertSame([$tersisa->id], $layanan->kandidat($rapat)->pluck('id')->all());

        // Undian berikutnya tidak pernah mengembalikan peserta yang sudah keluar.
        foreach (range(1, 5) as $ulang) {
            $this->assertSame($tersisa->id, $layanan->undi($rapat)?->id);
        }
    }

    public function test_menang_mendapat_urutan_dan_masuk_audit(): void
    {
        $rapat = $this->rapat();
        $peserta = $this->peserta($rapat, 1);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $pertama = (new Doorprize)->putuskan($rapat, $peserta, true);
        $kedua = (new Doorprize)->putuskan($rapat, $this->peserta($rapat, 2), true);

        $this->assertSame(DoorprizePemenang::MENANG, $pertama->hasil);
        $this->assertSame(1, $pertama->urutan);
        $this->assertSame(2, $kedua->urutan);
        $this->assertSame($admin->id, $pertama->dicatat_oleh);
        $this->assertSame(2, LogAudit::query()->where('entitas', 'doorprize_pemenang')->count());
    }

    public function test_gugur_tidak_mendapat_urutan_dan_tetap_tercatat(): void
    {
        $rapat = $this->rapat();
        $peserta = $this->peserta($rapat, 1);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $baris = (new Doorprize)->putuskan($rapat, $peserta, false);

        $this->assertSame(DoorprizePemenang::GUGUR, $baris->hasil);
        $this->assertNull($baris->urutan);
        $this->assertSame(0, (new Doorprize)->jumlahMenang($rapat));
        $this->assertSame(2, (new Doorprize)->sisa($rapat));
    }

    public function test_sisa_berkurang_sesuai_jumlah_pemenang_rapat(): void
    {
        $rapat = $this->rapat(['jumlah_pemenang_doorprize' => 1]);
        $layanan = new Doorprize;

        $this->assertSame(1, $layanan->target($rapat));
        $this->assertSame(1, $layanan->sisa($rapat));

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $layanan->putuskan($rapat, $this->peserta($rapat, 1), true);

        $this->assertSame(0, $layanan->sisa($rapat));
    }

    public function test_halaman_doorprize_hanya_untuk_admin(): void
    {
        $this->get(route('doorprize.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('doorprize.index'))
            ->assertOk()
            ->assertSee('Doorprize');

        foreach (['helpdesk', 'keuangan', 'scanner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('doorprize.index'))
                ->assertForbidden();
        }
    }

    public function test_komponen_undi_menampilkan_kandidat_lalu_menyimpan_kemenangan(): void
    {
        $rapat = $this->rapat();
        $peserta = $this->peserta($rapat, 1);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('doorprize-undian')
            ->call('undi')
            ->assertSee('Konfirmasi kehadiran')
            ->assertSee($peserta->anggota->nama)
            ->call('putuskan', true)
            ->assertSee('menang.')
            ->assertDontSee('Konfirmasi kehadiran');

        $baris = DoorprizePemenang::query()->sole();

        $this->assertSame($peserta->id, $baris->peserta_id);
        $this->assertSame(DoorprizePemenang::MENANG, $baris->hasil);
    }

    public function test_komponen_menandai_gugur_dan_batal_tidak_menyimpan(): void
    {
        $rapat = $this->rapat();
        $this->peserta($rapat, 1);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('doorprize-undian')
            ->call('undi')
            ->call('batal')
            ->assertDontSee('Konfirmasi kehadiran')
            ->assertSet('kandidatId', null);

        $this->assertSame(0, DoorprizePemenang::query()->count());

        Livewire::test('doorprize-undian')
            ->call('undi')
            ->call('putuskan', false)
            ->assertSee('gugur');

        $this->assertSame(DoorprizePemenang::GUGUR, DoorprizePemenang::query()->sole()->hasil);
    }

    public function test_undi_ditolak_saat_target_terpenuhi_atau_tanpa_peserta_hadir(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test('doorprize-undian')->assertSee('Belum ada rapat');

        $rapat = $this->rapat(['jumlah_pemenang_doorprize' => 1]);

        Livewire::test('doorprize-undian')
            ->call('undi')
            ->assertSee('Tidak ada peserta hadir yang bisa diundi.');

        $layanan = new Doorprize;
        $layanan->putuskan($rapat, $this->peserta($rapat, 1), true);

        Livewire::test('doorprize-undian')
            ->call('undi')
            ->assertSee('Jumlah pemenang sudah terpenuhi.');
    }

    public function test_jumlah_pemenang_disimpan_sebagai_parameter_rapat(): void
    {
        $rapat = $this->rapat(['status' => Rapat::DRAF]);
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = array_merge(Rapat::bawaan(), [
            'jumlah_pemenang_doorprize' => 25,
            'tier' => [],
        ]);

        $this->actingAs($admin)
            ->put(route('rapat.update', $rapat), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(25, Rapat::query()->sole()->jumlah_pemenang_doorprize);

        foreach ([0, 101] as $salah) {
            $this->actingAs($admin)
                ->put(route('rapat.update', $rapat), array_merge($payload, ['jumlah_pemenang_doorprize' => $salah]))
                ->assertSessionHasErrors('jumlah_pemenang_doorprize');
        }
    }
}
