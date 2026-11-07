<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\User;
use App\Services\ImportAnggota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportAnggotaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function baris(array $ubah = []): array
    {
        $data = [
            'no_anggota' => '020006000010399',
            'nama' => 'Anggota Impor',
            'no_hp' => '081200000000',
            'email' => null,
            'alamat' => null,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '15/11/2026',
            'tgl_keluar' => null,
            'simpanan_wajib' => 50000,
            'kolektibilitas' => 1,
            'pinjaman_lancar' => 'ya',
        ];

        return array_values(array_merge($data, $ubah));
    }

    /**
     * @param  list<array<int, mixed>>  $baris
     */
    private function berkas(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach (array_merge([ImportAnggota::KOLOM], $baris) as $r => $row) {
            foreach (array_values($row) as $c => $nilai) {
                if ($nilai === null) {
                    continue;
                }

                $sheet->getCell(Coordinate::stringFromColumnIndex($c + 1).($r + 1))
                    ->setValueExplicit($nilai, is_string($nilai) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'anggota.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_impor_nba_sama_memperbarui_tanpa_menambah_anggota(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([$this->baris()])])
            ->assertRedirect();

        $this->assertSame(1, session('impor')['dibuat']);

        $this->actingAs($admin)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([$this->baris(['nama' => 'Anggota Diperbarui'])])])
            ->assertRedirect();

        $this->assertSame(1, session('impor')['diperbarui']);
        $this->assertSame(1, Anggota::where('no_anggota', '020006000010399')->count());
        $this->assertSame('Anggota Diperbarui', Anggota::firstWhere('no_anggota', '020006000010399')->nama);
    }

    public function test_baris_tidak_valid_dilaporkan_tanpa_merusak_baris_valid(): void
    {
        $admin = $this->admin();
        Anggota::factory()->create(['no_anggota' => '020006000010397', 'nama' => 'Data Lama']);

        $this->actingAs($admin)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([
                $this->baris(['no_anggota' => '020006000010398', 'nama' => 'Baris Valid']),
                $this->baris(['no_anggota' => '123']),
            ])])
            ->assertRedirect();

        $hasil = session('impor');

        $this->assertSame(1, $hasil['dibuat']);
        $this->assertCount(1, $hasil['galat']);
        $this->assertSame(3, $hasil['galat'][0]['baris']);
        $this->assertDatabaseHas('anggota', ['no_anggota' => '020006000010398']);
        $this->assertDatabaseHas('anggota', ['no_anggota' => '020006000010397', 'nama' => 'Data Lama']);
    }

    public function test_kesalahan_email_dan_kolektibilitas_memberi_petunjuk_indonesia(): void
    {
        $this->actingAs($this->admin())
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([
                $this->baris(['email' => '-', 'kolektibilitas' => 6]),
            ])])
            ->assertRedirect();

        $pesan = session('impor')['galat'][0]['pesan'];

        $this->assertStringContainsString('kosongkan jika tidak ada', $pesan);
        $this->assertStringContainsString('antara 0 sampai 5', $pesan);
        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_kolektibilitas_nol_diterima_untuk_anggota_tanpa_pinjaman(): void
    {
        $this->actingAs($this->admin())
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([
                $this->baris(['kolektibilitas' => 0, 'pinjaman_lancar' => 'ya']),
            ])])
            ->assertRedirect();

        $this->assertSame(1, session('impor')['dibuat']);
        $this->assertDatabaseHas('anggota', [
            'no_anggota' => '020006000010399',
            'kolektibilitas' => 0,
            'pinjaman_lancar' => true,
        ]);
    }

    public function test_nba_yang_kehilangan_nol_awal_ditolak(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([
                $this->baris(['no_anggota' => 20006000010399]),
            ])])
            ->assertRedirect();

        $hasil = session('impor');

        $this->assertSame(0, $hasil['dibuat']);
        $this->assertStringContainsString('Teks 15 digit', $hasil['galat'][0]['pesan']);
        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_nomor_hp_dibersihkan_menjadi_digit_saja(): void
    {
        $this->actingAs($this->admin())
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([
                $this->baris(['no_hp' => 'WA: 0812-3456-7890']),
            ])])
            ->assertRedirect();

        $this->assertSame(1, session('impor')['dibuat']);
        $this->assertDatabaseHas('anggota', [
            'no_anggota' => '020006000010399',
            'no_hp' => '081234567890',
        ]);
    }

    public function test_helpdesk_tidak_boleh_mengimpor(): void
    {
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($helpdesk)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([$this->baris()])])
            ->assertForbidden();

        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_dialog_impor_memberi_status_proses_untuk_berkas_besar(): void
    {
        $this->actingAs($this->admin())
            ->get(route('anggota.index'))
            ->assertOk()
            ->assertSee('id="prosesImporAnggota"', false)
            ->assertSee('Data sedang diunggah dan diproses')
            ->assertSee('11.000 baris dapat memerlukan beberapa menit')
            ->assertSee('Unduh Template XLSX')
            ->assertDontSee('Aturan pengisian penting')
            ->assertSee('id="tombolImporAnggota"', false)
            ->assertSee("form.addEventListener('submit'", false);
    }

    public function test_template_xlsx_dapat_diunduh(): void
    {
        $respons = $this->actingAs($this->admin())
            ->get(route('anggota.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'template-anggota').'.xlsx';
        file_put_contents($path, $respons->streamedContent());
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(['Anggota', 'Petunjuk'], $spreadsheet->getSheetNames());
        $this->assertSame('email', $spreadsheet->getSheetByName('Petunjuk')->getCell('A5')->getValue());
        $this->assertStringContainsString('tidak memiliki pinjaman', $spreadsheet->getSheetByName('Petunjuk')->getCell('E13')->getValue());
        $this->assertSame('list', $spreadsheet->getSheetByName('Anggota')->getCell('K2')->getDataValidation()->getType());

        unlink($path);
    }
}
