<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\LogAudit;
use App\Models\Shu;
use App\Models\ShuPublik;
use App\Models\User;
use App\Services\ImportShu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * BR-011: SHU diimpor Admin per tahun buku, dan cek publik dibuka lewat tombol
 * Admin, bukan dari status rapat.
 */
class ShuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function anggota(string $nba = '020006000010399', string $nama = 'Anggota Impor'): Anggota
    {
        return Anggota::create([
            'no_anggota' => $nba,
            'nama' => $nama,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
            'simpanan_wajib' => 0,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function baris(string $nba = '020006000010399', mixed $saham = 150000, mixed $pinjaman = 75000): array
    {
        return [$nba, $saham, $pinjaman];
    }

    /**
     * @param  list<array<int, mixed>>  $baris
     */
    private function berkas(array $baris, ?array $kepala = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach (array_merge([$kepala ?? ImportShu::KOLOM], $baris) as $r => $row) {
            foreach (array_values($row) as $c => $nilai) {
                if ($nilai === null) {
                    continue;
                }

                $sheet->getCell(Coordinate::stringFromColumnIndex($c + 1).($r + 1))
                    ->setValueExplicit($nilai, is_string($nilai) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'impor-shu').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'shu.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_impor_menyimpan_shu_per_tahun_buku_dengan_total_dari_komponen(): void
    {
        $this->anggota();

        $this->actingAs($this->admin())
            ->post(route('shu.impor'), [
                'tahun_buku' => 2026,
                'berkas' => $this->berkas([$this->baris()]),
            ])
            ->assertRedirect(route('shu.index', ['tahun_buku' => 2026]))
            ->assertSessionHas('impor');

        $shu = Shu::query()->sole();

        $this->assertSame(2026, $shu->tahun_buku);
        $this->assertSame(150000.0, (float) $shu->balas_jasa_saham);
        $this->assertSame(75000.0, (float) $shu->balas_jasa_pinjaman);
        $this->assertSame(225000.0, $shu->total());
        $this->assertSame(1, LogAudit::query()->where('entitas', 'shu')->where('aksi', 'buat')->count());
    }

    public function test_impor_ulang_memperbarui_baris_tanpa_duplikat_dan_diaudit(): void
    {
        $this->anggota();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([$this->baris()]),
        ]);

        $this->actingAs($admin)->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([$this->baris(saham: 200000)]),
        ])->assertSessionHas('impor', fn ($hasil) => $hasil['diperbarui'] === 1 && $hasil['dibuat'] === 0);

        $this->assertSame(1, Shu::query()->count());
        $this->assertSame(200000.0, (float) Shu::query()->sole()->balas_jasa_saham);
        $this->assertSame(1, LogAudit::query()->where('entitas', 'shu')->where('aksi', 'ubah')->count());
    }

    public function test_tahun_buku_lain_tidak_menimpa_tahun_sebelumnya(): void
    {
        $this->anggota();
        $admin = $this->admin();

        foreach ([2025, 2026] as $tahun) {
            $this->actingAs($admin)->post(route('shu.impor'), [
                'tahun_buku' => $tahun,
                'berkas' => $this->berkas([$this->baris()]),
            ]);
        }

        $this->assertSame(2, Shu::query()->count());
        $this->assertSame([2025, 2026], Shu::query()->orderBy('tahun_buku')->pluck('tahun_buku')->all());
    }

    public function test_nba_tidak_ada_di_master_ditolak_tanpa_menghentikan_baris_lain(): void
    {
        $this->anggota('020006000010399', 'Ada');
        $this->anggota('020006000010400', 'Juga Ada');

        $hasil = $this->actingAs($this->admin())->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([
                $this->baris('020006000010399'),
                $this->baris('020006000019999'),
                $this->baris('020006000010400'),
            ]),
        ])->assertSessionHas('impor')->getSession()->get('impor');

        $this->assertSame(2, $hasil['dibuat']);
        $this->assertCount(1, $hasil['galat']);
        $this->assertStringContainsString('belum ada di master anggota', $hasil['galat'][0]['pesan']);
        $this->assertSame(2, Shu::query()->count());
    }

    public function test_nominal_bukan_angka_ditolak_per_baris(): void
    {
        $this->anggota();

        $hasil = $this->actingAs($this->admin())->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([$this->baris(saham: 'seratus ribu')]),
        ])->getSession()->get('impor');

        $this->assertSame(0, $hasil['dibuat']);
        $this->assertCount(1, $hasil['galat']);
        $this->assertSame(0, Shu::query()->count());
    }

    public function test_sel_nominal_kosong_dianggap_nol(): void
    {
        $this->anggota();

        $this->actingAs($this->admin())->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([$this->baris(saham: null, pinjaman: 50000)]),
        ]);

        $shu = Shu::query()->sole();
        $this->assertSame(0.0, (float) $shu->balas_jasa_saham);
        $this->assertSame(50000.0, (float) $shu->balas_jasa_pinjaman);
    }

    public function test_judul_kolom_tidak_lengkap_ditolak(): void
    {
        $this->anggota();

        $hasil = $this->actingAs($this->admin())->post(route('shu.impor'), [
            'tahun_buku' => 2026,
            'berkas' => $this->berkas([$this->baris()], ['no_anggota', 'balas_jasa_saham']),
        ])->getSession()->get('impor');

        $this->assertSame(1, $hasil['galat'][0]['baris']);
        $this->assertSame(0, Shu::query()->count());
    }

    public function test_impor_tanpa_berkas_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('shu.impor'), ['tahun_buku' => 2026])
            ->assertSessionHasErrors('berkas');
    }

    public function test_admin_menginput_shu_manual_dengan_nba_bertitik(): void
    {
        $anggota = $this->anggota();

        $this->actingAs($this->admin())
            ->post(route('shu.manual.simpan'), [
                'tahun_buku' => 2026,
                'no_anggota' => '020006.000.010.399',
                'balas_jasa_saham' => 125000,
                'balas_jasa_pinjaman' => 50000,
            ])
            ->assertRedirect(route('shu.index', ['tahun_buku' => 2026]));

        $this->assertDatabaseHas('shu', [
            'anggota_id' => $anggota->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 125000,
            'balas_jasa_pinjaman' => 50000,
        ]);

        $audit = LogAudit::query()->where('entitas', 'shu')->where('aksi', 'buat')->sole();
        $this->assertSame('input manual', $audit->alasan);
    }

    public function test_input_manual_menolak_nba_tidak_dikenal_dan_data_ganda(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('shu.manual.simpan'), [
                'tahun_buku' => 2026,
                'no_anggota' => '020006000019999',
                'balas_jasa_saham' => 1,
                'balas_jasa_pinjaman' => 0,
            ])
            ->assertSessionHasErrors('no_anggota');

        $this->anggota();
        $data = [
            'tahun_buku' => 2026,
            'no_anggota' => '020006000010399',
            'balas_jasa_saham' => 1,
            'balas_jasa_pinjaman' => 0,
        ];

        $this->actingAs($admin)->post(route('shu.manual.simpan'), $data);
        $this->actingAs($admin)->post(route('shu.manual.simpan'), $data)
            ->assertSessionHasErrors('no_anggota');

        $this->assertDatabaseCount('shu', 1);
    }

    public function test_admin_mengoreksi_shu_dengan_alasan_dan_audit_sebelum_sesudah(): void
    {
        $anggota = $this->anggota();
        $shu = Shu::create([
            'anggota_id' => $anggota->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 25000,
        ]);

        $this->actingAs($this->admin())
            ->put(route('shu.update', $shu), [
                'balas_jasa_saham' => 150000,
                'balas_jasa_pinjaman' => 30000,
                'alasan' => 'Koreksi hasil rapat final',
            ])
            ->assertRedirect(route('shu.index', ['tahun_buku' => 2026]));

        $audit = LogAudit::query()->where('entitas', 'shu')->where('aksi', 'ubah')->sole();
        $this->assertSame(100000.0, (float) $audit->sebelum['balas_jasa_saham']);
        $this->assertSame(150000.0, (float) $audit->sesudah['balas_jasa_saham']);
        $this->assertSame('Koreksi hasil rapat final', $audit->alasan);
        $this->assertSame(180000.0, $shu->refresh()->total());
    }

    public function test_koreksi_shu_membutuhkan_alasan_dan_perubahan_nominal(): void
    {
        $shu = Shu::create([
            'anggota_id' => $this->anggota()->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 25000,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('shu.update', $shu), [
            'balas_jasa_saham' => 150000,
            'balas_jasa_pinjaman' => 25000,
            'alasan' => 'pendek',
        ])->assertSessionHasErrors('alasan');

        $this->actingAs($admin)->put(route('shu.update', $shu), [
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 25000,
            'alasan' => 'Tidak ada perubahan nominal',
        ])->assertSessionHasErrors('shu');

        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_role_lain_tidak_dapat_input_atau_koreksi_shu_manual(): void
    {
        $shu = Shu::create([
            'anggota_id' => $this->anggota()->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 0,
        ]);
        $keuangan = User::factory()->create(['role' => 'keuangan']);

        $this->actingAs($keuangan)->post(route('shu.manual.simpan'), [
            'tahun_buku' => 2026,
            'no_anggota' => '020006000010399',
            'balas_jasa_saham' => 1,
            'balas_jasa_pinjaman' => 0,
        ])->assertForbidden();

        $this->actingAs($keuangan)->put(route('shu.update', $shu), [
            'balas_jasa_saham' => 1,
            'balas_jasa_pinjaman' => 0,
            'alasan' => 'Tidak berwenang mengubah',
        ])->assertForbidden();

        $this->assertSame(100000.0, (float) $shu->refresh()->balas_jasa_saham);
    }

    public function test_halaman_shu_hanya_untuk_admin(): void
    {
        $this->get(route('shu.index'))->assertRedirect(route('login'));

        $this->actingAs($this->admin())->get(route('shu.index'))->assertOk()->assertSee('Data SHU');

        foreach (['helpdesk', 'keuangan', 'scanner'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('shu.index'))
                ->assertForbidden();
        }
    }

    public function test_role_selain_admin_tidak_boleh_mengimpor_atau_membuka_publik(): void
    {
        $this->anggota();
        $keuangan = User::factory()->create(['role' => 'keuangan']);

        $this->actingAs($keuangan)
            ->post(route('shu.impor'), ['tahun_buku' => 2026, 'berkas' => $this->berkas([$this->baris()])])
            ->assertForbidden();

        $this->actingAs($keuangan)
            ->post(route('shu.publik.buka'), ['tahun_buku' => 2026])
            ->assertForbidden();

        $this->assertSame(0, Shu::query()->count());
        $this->assertSame(0, ShuPublik::query()->count());
    }

    public function test_buka_cek_publik_mencatat_pembuka_dan_audit(): void
    {
        $this->anggota();
        $admin = $this->admin();

        Shu::create([
            'anggota_id' => Anggota::query()->sole()->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('shu.publik.buka'), ['tahun_buku' => 2026])
            ->assertSessionHas('status');

        $publik = ShuPublik::query()->sole();

        $this->assertTrue($publik->aktif);
        $this->assertSame($admin->id, $publik->dibuka_oleh);
        $this->assertNotNull($publik->dibuka_at);
        $this->assertNull($publik->ditutup_at);
        $this->assertSame(1, LogAudit::query()->where('entitas', 'shu_publik')->where('aksi', 'buka')->count());
    }

    public function test_buka_cek_publik_tanpa_data_shu_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('shu.publik.buka'), ['tahun_buku' => 2026])
            ->assertSessionHasErrors('publik');

        $this->assertSame(0, ShuPublik::query()->count());
    }

    public function test_tutup_cek_publik_menonaktifkan_dan_diaudit(): void
    {
        $this->anggota();
        $admin = $this->admin();

        Shu::create([
            'anggota_id' => Anggota::query()->sole()->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 0,
        ]);

        $this->actingAs($admin)->post(route('shu.publik.buka'), ['tahun_buku' => 2026]);
        $this->actingAs($admin)->post(route('shu.publik.tutup'), ['tahun_buku' => 2026])
            ->assertSessionHas('status');

        $publik = ShuPublik::query()->sole();

        $this->assertFalse($publik->aktif);
        $this->assertNotNull($publik->ditutup_at);
        $this->assertSame(1, LogAudit::query()->where('entitas', 'shu_publik')->where('aksi', 'tutup')->count());
    }

    public function test_tutup_cek_publik_yang_belum_dibuka_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('shu.publik.tutup'), ['tahun_buku' => 2026])
            ->assertSessionHasErrors('publik');
    }

    public function test_halaman_menampilkan_total_dan_status_publik_per_tahun(): void
    {
        $this->anggota();

        Shu::create([
            'anggota_id' => Anggota::query()->sole()->id,
            'tahun_buku' => 2026,
            'balas_jasa_saham' => 100000,
            'balas_jasa_pinjaman' => 25000,
        ]);

        $this->actingAs($this->admin())
            ->get(route('shu.index', ['tahun_buku' => 2026]))
            ->assertOk()
            ->assertSee('Rp125.000')
            ->assertSee('Tertutup')
            ->assertSee('Buka cek publik');

        $this->actingAs($this->admin())
            ->get(route('shu.index', ['tahun_buku' => 2030]))
            ->assertOk()
            ->assertSee('Belum ada data SHU untuk tahun buku 2030');
    }

    public function test_template_xlsx_bisa_diunduh(): void
    {
        $this->actingAs($this->admin())
            ->get(route('shu.template'))
            ->assertOk()
            ->assertDownload('template-shu.xlsx');
    }
}
