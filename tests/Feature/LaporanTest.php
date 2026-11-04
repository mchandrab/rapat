<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\DoorprizePemenang;
use App\Models\KasScanner;
use App\Models\LogUndangan;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\Shu;
use App\Models\TransportPembayaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

/**
 * FR-011: laporan rekap per rapat. Bagian mengikuti role (Admin semua,
 * Keuangan kas & transport, Helpdesk terbatas) dan ekspor XLSX memakai angka
 * yang sama dengan halaman.
 */
class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private function rapat(array $ubah = []): Rapat
    {
        return Rapat::create(array_merge(Rapat::bawaan(), [
            'status' => Rapat::BERLANGSUNG,
            'tgl_rapat' => '2026-11-15',
            'persen_kuorum' => 50,
            'tambahan_kuorum' => 0,
            'jumlah_pemenang_doorprize' => 2,
        ], $ubah));
    }

    private function anggota(int $urut): Anggota
    {
        return Anggota::create([
            'no_anggota' => str_pad((string) $urut, 15, '0', STR_PAD_LEFT),
            'nama' => 'Anggota '.$urut,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
            'simpanan_wajib' => 0,
        ]);
    }

    private function peserta(Rapat $rapat, int $urut, string $status = Peserta::VERIFIED): Peserta
    {
        return Peserta::create([
            'rapat_id' => $rapat->id,
            'anggota_id' => $this->anggota($urut)->id,
            'hasil_kelayakan' => Peserta::LAYAK,
            'status_verifikasi' => $status,
            'snapshot_konfigurasi' => [],
        ]);
    }

    /**
     * Satu skenario lengkap: 2 peserta, 1 hadir, 1 kas Scanner, undangan dari
     * dua petugas, SHU dua tahun buku, dan 1 pemenang + 1 gugur doorprize.
     */
    private function skenario(Rapat $rapat): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Uji']);
        $helpdesk = User::factory()->create(['role' => 'helpdesk', 'name' => 'Helpdesk Uji']);
        $scanner = User::factory()->create(['role' => 'scanner', 'name' => 'Scanner Uji']);

        $hadir = $this->peserta($rapat, 1);
        $pulang = $this->peserta($rapat, 2, Peserta::PENDING);

        Presensi::create([
            'peserta_id' => $hadir->id,
            'datang_at' => '2026-11-15 07:30:00',
            'dicatat_oleh' => $scanner->id,
            'nominal_transport_final' => 150000,
        ]);

        $kas = KasScanner::create([
            'rapat_id' => $rapat->id,
            'scanner_user_id' => $scanner->id,
            'kas_awal' => 1000000,
            'diserahkan_oleh' => $admin->id,
            'status' => KasScanner::AKTIF,
        ]);

        TransportPembayaran::create([
            'peserta_id' => $hadir->id,
            'kas_scanner_id' => $kas->id,
            'dibayar_oleh' => $scanner->id,
            'nominal' => 150000,
            'dibayar_pada' => '2026-11-15 07:31:00',
            'kode_resi' => 'RESI-UJI-1',
        ]);

        LogUndangan::create([
            'rapat_id' => $rapat->id,
            'peserta_id' => $hadir->id,
            'petugas_user_id' => $helpdesk->id,
            'metode' => LogUndangan::METODE_WA,
            'status' => LogUndangan::DITERIMA_API,
            'waktu_aksi' => '2026-11-10 09:00:00',
        ]);
        LogUndangan::create([
            'rapat_id' => $rapat->id,
            'peserta_id' => $pulang->id,
            'petugas_user_id' => $admin->id,
            'metode' => LogUndangan::METODE_CETAK,
            'status' => LogUndangan::DICETAK,
            'waktu_aksi' => '2026-11-10 09:05:00',
        ]);

        Shu::create(['anggota_id' => $hadir->anggota_id, 'tahun_buku' => 2026, 'balas_jasa_saham' => 150000, 'balas_jasa_pinjaman' => 75000]);
        Shu::create(['anggota_id' => $pulang->anggota_id, 'tahun_buku' => 2026, 'balas_jasa_saham' => 50000, 'balas_jasa_pinjaman' => 25000]);
        Shu::create(['anggota_id' => $hadir->anggota_id, 'tahun_buku' => 2025, 'balas_jasa_saham' => 100000, 'balas_jasa_pinjaman' => 0]);

        DoorprizePemenang::create([
            'rapat_id' => $rapat->id,
            'peserta_id' => $hadir->id,
            'hasil' => DoorprizePemenang::MENANG,
            'urutan' => 1,
            'dicatat_oleh' => $admin->id,
        ]);
        DoorprizePemenang::create([
            'rapat_id' => $rapat->id,
            'peserta_id' => $pulang->id,
            'hasil' => DoorprizePemenang::GUGUR,
            'dicatat_oleh' => $admin->id,
        ]);
    }

    private function pengguna(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * @return array<int, string>
     */
    private function isiXlsx(string $isi): array
    {
        $path = tempnam(sys_get_temp_dir(), 'laporan');
        file_put_contents($path, $isi);

        $sheet = (new Xlsx)->load($path)->getActiveSheet();
        $hasil = [];

        foreach ($sheet->toArray() as $baris) {
            foreach ($baris as $sel) {
                if ($sel !== null && $sel !== '') {
                    $hasil[] = (string) $sel;
                }
            }
        }

        @unlink($path);

        return $hasil;
    }

    public function test_admin_melihat_semua_bagian_dengan_angka_nyata(): void
    {
        $rapat = $this->rapat();
        $this->skenario($rapat);

        $this->actingAs($this->pengguna('admin'))
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Peserta &amp; verifikasi', false)
            ->assertSee('Undangan per petugas')
            ->assertSee('Presensi &amp; transport per petugas', false)
            ->assertSee('Kas per Scanner')
            ->assertSee('SHU per tahun buku')
            ->assertSee('Doorprize')
            ->assertSee('Kuorum')
            // Kuorum: populasi 2, target 1, hadir 1.
            ->assertSee('Rp850.000')
            ->assertSee('Rp150.000')
            ->assertSee('Rp300.000')
            ->assertSee('Anggota 1');
    }

    public function test_keuangan_hanya_melihat_kas_transport_dan_kuorum(): void
    {
        $rapat = $this->rapat();
        $this->skenario($rapat);

        $this->actingAs($this->pengguna('keuangan'))
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Kas per Scanner')
            ->assertSee('Presensi &amp; transport per petugas', false)
            ->assertSee('Kuorum')
            ->assertDontSee('SHU per tahun buku')
            ->assertDontSee('Peserta &amp; verifikasi', false)
            ->assertDontSee('Undangan per petugas')
            ->assertDontSee('Doorprize');
    }

    public function test_helpdesk_hanya_melihat_peserta_dan_undangan(): void
    {
        $rapat = $this->rapat();
        $this->skenario($rapat);

        $this->actingAs($this->pengguna('helpdesk'))
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Peserta &amp; verifikasi', false)
            ->assertSee('Undangan per petugas')
            ->assertDontSee('Kas per Scanner')
            ->assertDontSee('SHU per tahun buku')
            ->assertDontSee('Doorprize')
            ->assertDontSee('Presensi &amp; transport per petugas', false);
    }

    public function test_scanner_tidak_boleh_membuka_laporan(): void
    {
        $this->actingAs($this->pengguna('scanner'))
            ->get(route('laporan.index'))
            ->assertForbidden();

        $this->actingAs($this->pengguna('scanner'))
            ->get(route('laporan.ekspor'))
            ->assertForbidden();
    }

    public function test_pilihan_rapat_mengikuti_query_string(): void
    {
        $lama = $this->rapat(['judul' => 'RAT 2025', 'tgl_rapat' => '2025-11-15']);
        $baru = $this->rapat(['judul' => 'RAT 2026', 'tgl_rapat' => '2026-11-15']);
        $this->skenario($baru);

        $this->actingAs($this->pengguna('admin'))
            ->get(route('laporan.index', ['rapat' => $lama->id]))
            ->assertOk()
            ->assertSee('Belum ada presensi tercatat.')
            ->assertSee('Belum ada kas Scanner pada rapat ini.');

        $this->actingAs($this->pengguna('admin'))
            ->get(route('laporan.index', ['rapat' => $baru->id]))
            ->assertOk()
            ->assertSee('Rp850.000')
            ->assertDontSee('Belum ada presensi tercatat.');
    }

    public function test_ekspor_admin_berisi_semua_bagian(): void
    {
        $rapat = $this->rapat(['judul' => 'Rapat Anggota Khusus 2026']);
        $this->skenario($rapat);

        $respons = $this->actingAs($this->pengguna('admin'))->get(route('laporan.ekspor'));

        $respons->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('laporan-rapat-anggota-khusus-2026-2026-11-15.xlsx', (string) $respons->headers->get('content-disposition'));

        $isi = implode(' | ', $this->isiXlsx($respons->streamedContent()));

        foreach (['Kuorum', 'Peserta & verifikasi', 'Undangan per petugas', 'Presensi & transport per petugas', 'Kas per Scanner', 'SHU per tahun buku', 'Doorprize'] as $judul) {
            $this->assertStringContainsString($judul, $isi);
        }

        $this->assertStringContainsString('Scanner Uji', $isi);
        $this->assertStringContainsString('850,000', $isi);
        $this->assertStringContainsString('300,000', $isi);
    }

    public function test_ekspor_keuangan_tidak_memuat_shu_atau_peserta(): void
    {
        $rapat = $this->rapat();
        $this->skenario($rapat);

        $respons = $this->actingAs($this->pengguna('keuangan'))->get(route('laporan.ekspor'));
        $respons->assertOk();

        $isi = implode(' | ', $this->isiXlsx($respons->streamedContent()));

        $this->assertStringContainsString('Kas per Scanner', $isi);
        $this->assertStringNotContainsString('SHU per tahun buku', $isi);
        $this->assertStringNotContainsString('Peserta & verifikasi', $isi);
        $this->assertStringNotContainsString('Doorprize', $isi);
    }
}
