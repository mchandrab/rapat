<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\User;
use App\Services\ImportAnggota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

    public function test_helpdesk_tidak_boleh_mengimpor(): void
    {
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);

        $this->actingAs($helpdesk)
            ->post(route('anggota.impor'), ['berkas' => $this->berkas([$this->baris()])])
            ->assertForbidden();

        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_template_xlsx_dapat_diunduh(): void
    {
        $this->actingAs($this->admin())
            ->get(route('anggota.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
