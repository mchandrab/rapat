<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\DoorprizeHadiah;
use App\Models\DoorprizePemenang;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\User;
use App\Services\Doorprize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DoorprizeTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::BERLANGSUNG,
            'tgl_rapat' => '2026-11-15',
        ], $ubah));
    }

    private function hadiah(Rapat $rapat, array $ubah = []): DoorprizeHadiah
    {
        return DoorprizeHadiah::create(array_merge([
            'rapat_id' => $rapat->id,
            'nama' => 'Kulkas',
            'jumlah' => 2,
            'keterangan' => 'Hadiah utama',
        ], $ubah));
    }

    private function peserta(Rapat $rapat, int $urut, bool $hadir = true): Peserta
    {
        $anggota = Anggota::create([
            'no_anggota' => str_pad((string) $urut, 15, '0', STR_PAD_LEFT),
            'nama' => 'Peserta '.$urut,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
        ]);
        $peserta = Peserta::create([
            'rapat_id' => $rapat->id,
            'anggota_id' => $anggota->id,
            'hasil_kelayakan' => Peserta::LAYAK,
            'status_verifikasi' => Peserta::VERIFIED,
            'snapshot_konfigurasi' => [],
        ]);

        if ($hadir) {
            Presensi::create(['peserta_id' => $peserta->id, 'datang_at' => now(), 'nominal_transport_final' => 0]);
        }

        return $peserta;
    }

    public function test_kandidat_hanya_peserta_hadir_dan_belum_memiliki_hasil(): void
    {
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat);
        $hadir = $this->peserta($rapat, 1);
        $this->peserta($rapat, 2, false);

        $this->assertTrue((new Doorprize)->undi($rapat, $hadiah)->is($hadir));

        (new Doorprize)->putuskan($rapat, $hadir, $hadiah, false);
        $this->assertNull((new Doorprize)->undi($rapat, $hadiah));
    }

    public function test_menang_menyimpan_hadiah_snapshot_admin_audit_dan_mengurangi_sisa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat);
        $peserta = $this->peserta($rapat, 1);

        $this->actingAs($admin);
        $hasil = (new Doorprize)->putuskan($rapat, $peserta, $hadiah, true);

        $this->assertSame($hadiah->id, $hasil->doorprize_hadiah_id);
        $this->assertSame('Kulkas', $hasil->nama_hadiah_snapshot);
        $this->assertSame($admin->id, $hasil->dicatat_oleh);
        $this->assertSame(1, $hadiah->fresh()->sisa());
        $this->assertSame(2, $hadiah->fresh()->jumlah);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'doorprize', 'entitas' => 'doorprize_pemenang', 'entitas_id' => $hasil->id]);

        $hadiah->update(['nama' => 'Kulkas Baru']);
        $this->assertSame('Kulkas', $hasil->fresh()->nama_hadiah_snapshot);
    }

    public function test_gugur_tidak_memakai_stok_hadiah(): void
    {
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat, ['jumlah' => 1]);
        $hasil = (new Doorprize)->putuskan($rapat, $this->peserta($rapat, 1), $hadiah, false);

        $this->assertSame(DoorprizePemenang::GUGUR, $hasil->hasil);
        $this->assertNull($hasil->doorprize_hadiah_id);
        $this->assertSame(1, $hadiah->fresh()->sisa());
    }

    public function test_gerbang_status_undian_diterapkan_di_server(): void
    {
        foreach ([Rapat::DRAF, Rapat::SELESAI, Rapat::DIBATALKAN] as $index => $status) {
            $rapat = $this->rapat(['status' => $status]);
            $hadiah = $this->hadiah($rapat);
            $this->peserta($rapat, 10 + $index);

            try {
                (new Doorprize)->undi($rapat, $hadiah);
                $this->fail("Status {$status} seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('doorprize', $e->errors());
            }
        }

        $verifikasi = $this->rapat(['status' => Rapat::VERIFIKASI, 'presensi_dibuka_at' => null]);
        $hadiah = $this->hadiah($verifikasi);
        $peserta = $this->peserta($verifikasi, 20);
        $this->expectException(ValidationException::class);
        (new Doorprize)->undi($verifikasi, $hadiah);
    }

    public function test_verifikasi_setelah_presensi_dibuka_dan_berlangsung_diizinkan(): void
    {
        $verifikasi = $this->rapat(['status' => Rapat::VERIFIKASI, 'presensi_dibuka_at' => now()]);
        $hadiah = $this->hadiah($verifikasi);
        $this->peserta($verifikasi, 1);

        $this->assertNotNull((new Doorprize)->undi($verifikasi, $hadiah));
    }

    public function test_crud_hadiah_tervalidasi_dan_diaudit(): void
    {
        $rapat = $this->rapat();
        $layanan = new Doorprize;
        $hadiah = $layanan->tambahHadiah($rapat, ['nama' => 'Televisi', 'jumlah' => 3, 'keterangan' => null]);
        $layanan->ubahHadiah($hadiah, ['nama' => 'Smart TV', 'jumlah' => 2, 'keterangan' => '42 inci']);

        $this->assertDatabaseHas('doorprize_hadiah', ['id' => $hadiah->id, 'nama' => 'Smart TV', 'jumlah' => 2]);
        $this->assertSame(2, LogAudit::query()->where('entitas', 'doorprize_hadiah')->count());

        $layanan->hapusHadiah($hadiah);
        $this->assertDatabaseMissing('doorprize_hadiah', ['id' => $hadiah->id]);
        $this->assertDatabaseHas('log_audit', ['aksi' => 'hapus', 'entitas' => 'doorprize_hadiah']);
    }

    public function test_jumlah_tidak_boleh_di_bawah_pemenang_dan_hadiah_bersejarah_tidak_dapat_dihapus(): void
    {
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat, ['jumlah' => 1]);
        (new Doorprize)->putuskan($rapat, $this->peserta($rapat, 1), $hadiah, true);

        try {
            (new Doorprize)->ubahHadiah($hadiah, ['nama' => 'Kulkas', 'jumlah' => 0, 'keterangan' => null]);
            $this->fail('Jumlah di bawah pemenang seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('jumlah', $e->errors());
        }

        $this->expectException(ValidationException::class);
        (new Doorprize)->hapusHadiah($hadiah);
    }

    public function test_hadiah_habis_ditolak_dan_tetap_tampil_di_komponen(): void
    {
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat, ['jumlah' => 1]);
        (new Doorprize)->putuskan($rapat, $this->peserta($rapat, 1), $hadiah, true);

        Livewire::test('doorprize-undian')
            ->assertSee('Hadiah Habis')
            ->assertSee('Kulkas')
            ->assertSee('Tidak ada hadiah yang masih tersedia.');

        $this->expectException(ValidationException::class);
        (new Doorprize)->undi($rapat, $hadiah);
    }

    public function test_request_pemenang_terakhir_tidak_dapat_melebihi_stok(): void
    {
        $rapat = $this->rapat();
        $hadiah = $this->hadiah($rapat, ['jumlah' => 1]);
        $pertama = $this->peserta($rapat, 1);
        $kedua = $this->peserta($rapat, 2);
        $layanan = new Doorprize;
        $layanan->putuskan($rapat, $pertama, $hadiah, true);

        try {
            $layanan->putuskan($rapat, $kedua, $hadiah, true);
            $this->fail('Stok habis seharusnya ditolak setelah row diperiksa ulang.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hadiah', $e->errors());
        }

        $this->assertSame(1, DoorprizePemenang::query()->where('hasil', DoorprizePemenang::MENANG)->count());
    }

    public function test_satu_peserta_tidak_dapat_memiliki_hasil_kedua(): void
    {
        $rapat = $this->rapat();
        $hadiahA = $this->hadiah($rapat);
        $hadiahB = $this->hadiah($rapat, ['nama' => 'Sepeda']);
        $peserta = $this->peserta($rapat, 1);
        $layanan = new Doorprize;
        $layanan->putuskan($rapat, $peserta, $hadiahA, true);

        $this->expectException(ValidationException::class);
        $layanan->putuskan($rapat, $peserta, $hadiahB, true);
    }

    public function test_komponen_master_hadiah_menyimpan_tanpa_field_urutan(): void
    {
        $rapat = $this->rapat();

        Livewire::test('doorprize-hadiah', ['rapat' => $rapat])
            ->set('nama', 'Rice Cooker')
            ->set('jumlah', 4)
            ->set('keterangan', '2 liter')
            ->call('simpan')
            ->assertSee('Rice Cooker');

        $this->assertDatabaseHas('doorprize_hadiah', ['rapat_id' => $rapat->id, 'nama' => 'Rice Cooker', 'jumlah' => 4]);
        $this->assertFalse(Schema::hasColumn('doorprize_hadiah', 'urutan'));
    }

    public function test_hak_akses_halaman_doorprize_hanya_admin(): void
    {
        $this->get(route('doorprize.index'))->assertRedirect(route('login'));
        $this->get(route('doorprize.undian'))->assertRedirect(route('login'));
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('doorprize.index'))->assertOk();
        $this->actingAs($admin)->get(route('doorprize.undian'))->assertOk();

        foreach (['helpdesk', 'scanner', 'keuangan'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('doorprize.index'))->assertForbidden();
            $this->actingAs($user)->get(route('doorprize.undian'))->assertForbidden();
        }
    }

    public function test_master_hadiah_dikelola_di_doorprize_dan_undian_memiliki_halaman_terpisah(): void
    {
        $this->rapat();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('doorprize.index'))
            ->assertOk()
            ->assertSee('Master Hadiah Doorprize')
            ->assertSee('Buka Halaman Undian');

        $this->actingAs($admin)
            ->get(route('doorprize.undian'))
            ->assertOk()
            ->assertSee('UNDI SEKARANG')
            ->assertSee('Master Hadiah');

        $this->actingAs($admin)
            ->get(route('rapat.index'))
            ->assertOk()
            ->assertDontSee('Master Hadiah Doorprize');
    }

    public function test_migrasi_menghubungkan_pemenang_lama_ke_hadiah_arsip(): void
    {
        $migration = require database_path('migrations/2026_11_05_090000_create_doorprize_hadiah_and_migrate_results.php');
        $migration->down();

        $rapat = $this->rapat();
        $peserta = $this->peserta($rapat, 99);
        DB::table('doorprize_pemenang')->insert([
            'rapat_id' => $rapat->id,
            'peserta_id' => $peserta->id,
            'hasil' => 'menang',
            'urutan' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('doorprize_pemenang', [
            'rapat_id' => $rapat->id,
            'peserta_id' => $peserta->id,
            'nama_hadiah_snapshot' => 'Hadiah arsip (sebelum D-044)',
        ]);
        $this->assertDatabaseHas('doorprize_hadiah', ['rapat_id' => $rapat->id, 'jumlah' => 1]);
        $this->assertFalse(Schema::hasColumn('rapat', 'jumlah_pemenang_doorprize'));
        $this->assertFalse(Schema::hasColumn('doorprize_pemenang', 'urutan'));
    }
}
