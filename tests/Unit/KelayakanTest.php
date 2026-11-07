<?php

namespace Tests\Unit;

use App\Support\Kelayakan;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class KelayakanTest extends TestCase
{
    private function hitung(string $tglJa, string $tanggalAcuan = '2026-12-31'): ?float
    {
        return Kelayakan::minimumProporsional(Carbon::parse($tglJa), Carbon::parse($tanggalAcuan), 25000, 300000);
    }

    public function test_bulan_bergabung_dihitung_inklusif_sampai_desember(): void
    {
        $this->assertSame(50000.0, $this->hitung('2026-11-10'));
    }

    public function test_anggota_lama_dibatasi_minimum_tahunan(): void
    {
        $this->assertSame(300000.0, $this->hitung('2020-01-15'));
    }

    public function test_bergabung_sebelum_tahun_tanggal_acuan_tetap_dibatasi_minimum_tahunan(): void
    {
        $this->assertSame(300000.0, $this->hitung('2025-12-31'));
    }

    public function test_bergabung_setelah_tanggal_acuan_tidak_ada_kewajiban_dalam_rumus(): void
    {
        $this->assertSame(0.0, $this->hitung('2027-01-01'));
    }

    public function test_tanggal_kosong_tidak_dihitung(): void
    {
        $this->assertNull(Kelayakan::minimumProporsional(null, Carbon::parse('2026-10-31'), 25000, 300000));
    }

    public function test_anggota_baru_dihitung_sampai_tanggal_acuan_saldo(): void
    {
        $this->assertSame(25000.0, $this->hitung('2026-10-22', '2026-10-31'));
        $this->assertSame(250000.0, $this->hitung('2020-01-15', '2026-10-31'));
    }
}
