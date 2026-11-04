<?php

namespace Tests\Unit;

use App\Support\Kelayakan;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class KelayakanTest extends TestCase
{
    private function hitung(string $tglJa): ?float
    {
        return Kelayakan::minimumProporsional(Carbon::parse($tglJa), 2026, 25000, 300000);
    }

    public function test_bulan_bergabung_dihitung_inklusif_sampai_desember(): void
    {
        $this->assertSame(50000.0, $this->hitung('2026-11-10'));
    }

    public function test_anggota_lama_dibatasi_minimum_tahunan(): void
    {
        $this->assertSame(300000.0, $this->hitung('2020-01-15'));
    }

    public function test_bergabung_sebelum_tahun_acuan_tetap_dibatasi_minimum_tahunan(): void
    {
        $this->assertSame(300000.0, $this->hitung('2025-12-31'));
    }

    public function test_bergabung_setelah_tahun_acuan_tidak_ada_kewajiban(): void
    {
        $this->assertSame(0.0, $this->hitung('2027-01-01'));
    }

    public function test_tanggal_kosong_tidak_dihitung(): void
    {
        $this->assertNull(Kelayakan::minimumProporsional(null, 2026, 25000, 300000));
    }
}
