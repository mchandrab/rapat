<?php

namespace Tests\Unit;

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Services\PenilaianKelayakan;
use Tests\TestCase;

class PenilaianKelayakanTest extends TestCase
{
    private function rapat(array $ubah = []): Rapat
    {
        return new Rapat(array_merge(Rapat::bawaan(), [
            'tanggal_acuan_saldo' => '2026-12-31',
        ], $ubah));
    }

    private function anggota(array $ubah = []): Anggota
    {
        return new Anggota(array_merge([
            'no_anggota' => '020006000010399',
            'nama' => 'Uji Coba',
            'tgl_ja' => '2020-01-15',
            'simpanan_wajib' => 300000,
            'kolektibilitas' => 1,
            'pinjaman_lancar' => true,
            'status_anggota' => 'aktif',
        ], $ubah));
    }

    public function test_simpanan_di_bawah_minimum_tidak_layak(): void
    {
        $hasil = PenilaianKelayakan::nilai($this->anggota(['simpanan_wajib' => 100000]), $this->rapat());

        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
        $this->assertSame(300000.0, $hasil['minimum']);
    }

    public function test_kolektibilitas_melebihi_batas_tidak_layak(): void
    {
        $hasil = PenilaianKelayakan::nilai($this->anggota(['kolektibilitas' => 3]), $this->rapat(['batas_kolektibilitas' => 2]));

        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
    }

    public function test_anggota_tanpa_pinjaman_dengan_kolektibilitas_nol_tetap_layak(): void
    {
        $anggota = $this->anggota(['kolektibilitas' => 0, 'pinjaman_lancar' => true]);

        $this->assertSame(Peserta::LAYAK, PenilaianKelayakan::nilai($anggota, $this->rapat())['hasil']);
        $this->assertSame(Peserta::LAYAK, PenilaianKelayakan::nilai($anggota, $this->rapat(['mode_kelayakan' => 'B']))['hasil']);
    }

    public function test_mode_b_mewajibkan_pinjaman_lancar(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['pinjaman_lancar' => false]),
            $this->rapat(['mode_kelayakan' => 'B']),
        );

        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
    }

    public function test_kriteria_tambahan_pinjaman_lancar_dihormati(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['pinjaman_lancar' => false]),
            $this->rapat(['wajib_pinjaman_lancar' => true]),
        );

        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
    }

    public function test_proporsional_memakai_tarif_bulanan(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => '2026-11-10', 'simpanan_wajib' => 50000]),
            $this->rapat(),
        );

        $this->assertSame(Peserta::LAYAK, $hasil['hasil']);
        $this->assertSame(50000.0, $hasil['minimum']);
    }

    public function test_proporsional_dimatikan_memakai_minimum_tahunan(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => '2026-11-10', 'simpanan_wajib' => 50000]),
            $this->rapat(['gunakan_proporsional' => false]),
        );

        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
        $this->assertSame(300000.0, $hasil['minimum']);
    }

    public function test_tanggal_gabung_kosong_tidak_layak_karena_data_belum_lengkap(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => null, 'simpanan_wajib' => 100000]),
            $this->rapat(),
        );

        // D-033: keringanan proporsional tidak boleh ditebak saat tanggal kosong.
        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
        $this->assertContains('Tanggal jadi anggota belum lengkap', $hasil['alasan']);
        $this->assertCount(1, $hasil['alasan']);
    }

    public function test_tanggal_gabung_kosong_tanpa_proporsional_memakai_cek_simpanan(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => null, 'simpanan_wajib' => 300000]),
            $this->rapat(['gunakan_proporsional' => false]),
        );

        $this->assertSame(Peserta::LAYAK, $hasil['hasil']);
        $this->assertSame([], $hasil['alasan']);
    }

    public function test_gabung_setelah_tanggal_acuan_tidak_layak(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => '2027-01-05', 'simpanan_wajib' => 0]),
            $this->rapat(),
        );

        $this->assertSame(0.0, $hasil['minimum']);
        $this->assertSame(Peserta::TIDAK_LAYAK, $hasil['hasil']);
        $this->assertContains('Tanggal jadi anggota setelah tanggal acuan saldo', $hasil['alasan']);
    }

    public function test_saldo_akhir_oktober_membuat_anggota_baru_oktober_layak_satu_bulan(): void
    {
        $hasil = PenilaianKelayakan::nilai(
            $this->anggota(['tgl_ja' => '2026-10-22', 'simpanan_wajib' => 25000]),
            $this->rapat([
                'tanggal_acuan_saldo' => '2026-10-31',
                'minimal_simpanan_tahunan' => 250000,
            ]),
        );

        $this->assertSame(Peserta::LAYAK, $hasil['hasil']);
        $this->assertSame(25000.0, $hasil['minimum']);
    }

    public function test_status_nonaktif_tidak_memengaruhi_kelayakan(): void
    {
        $hasil = PenilaianKelayakan::nilai($this->anggota(['status_anggota' => 'nonaktif']), $this->rapat());

        $this->assertSame(Peserta::LAYAK, $hasil['hasil']);
    }
}
