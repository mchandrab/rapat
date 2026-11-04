<?php

namespace Tests\Unit;

use App\Models\Rapat;
use App\Models\RapatTransportTier;
use App\Services\PenghitungTransport;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * T-SCAN-04 / BR-008: acuan keterlambatan adalah jam mulai RAT, bukan jam presensi.
 */
class PenghitungTransportTest extends TestCase
{
    /**
     * @param  array<int, array{menit_mulai: int, menit_akhir: int, potongan: float}>  $tier
     */
    private function rapat(array $tier = []): Rapat
    {
        $rapat = new Rapat(array_merge(Rapat::bawaan(), [
            'tgl_rapat' => '2026-11-15',
            'jam_mulai_rat' => '08:00',
            'toleransi_menit' => 60,
            'transport_dasar' => 100000,
        ]));

        $rapat->setRelation('tier', collect($tier)
            ->map(fn (array $baris, int $i) => new RapatTransportTier($baris + ['urutan' => $i + 1]))
            ->values());

        return $rapat;
    }

    public function test_tepat_batas_toleransi_belum_dipotong(): void
    {
        $hasil = PenghitungTransport::hitung($this->rapat([['menit_mulai' => 1, 'menit_akhir' => 15, 'potongan' => 25000]]), Carbon::parse('2026-11-15 09:00:00'));

        $this->assertSame(0, $hasil['menit_kena']);
        $this->assertNull($hasil['tier']);
        $this->assertSame(100000.0, $hasil['nominal']);
    }

    public function test_satu_menit_setelah_toleransi_masuk_tier(): void
    {
        $hasil = PenghitungTransport::hitung($this->rapat([['menit_mulai' => 1, 'menit_akhir' => 15, 'potongan' => 25000]]), Carbon::parse('2026-11-15 09:01:00'));

        $this->assertSame(1, $hasil['menit_kena']);
        $this->assertSame(25000.0, $hasil['potongan']);
        $this->assertSame(75000.0, $hasil['nominal']);
    }

    public function test_datang_lebih_awal_tetap_nominal_penuh(): void
    {
        $hasil = PenghitungTransport::hitung($this->rapat([['menit_mulai' => 1, 'menit_akhir' => 15, 'potongan' => 25000]]), Carbon::parse('2026-11-15 07:30:00'));

        $this->assertSame(0, $hasil['menit_kena']);
        $this->assertSame(100000.0, $hasil['nominal']);
    }

    public function test_potongan_melebihi_dasar_menjadi_nol(): void
    {
        $hasil = PenghitungTransport::hitung($this->rapat([['menit_mulai' => 1, 'menit_akhir' => 60, 'potongan' => 150000]]), Carbon::parse('2026-11-15 09:30:00'));

        $this->assertSame(0.0, $hasil['nominal']);
    }

    public function test_tier_kedua_dipakai_setelah_tier_pertama(): void
    {
        $hasil = PenghitungTransport::hitung(
            $this->rapat([
                ['menit_mulai' => 1, 'menit_akhir' => 15, 'potongan' => 25000],
                ['menit_mulai' => 16, 'menit_akhir' => 60, 'potongan' => 50000],
            ]),
            Carbon::parse('2026-11-15 09:30:00'),
        );

        $this->assertSame(30, $hasil['menit_kena']);
        $this->assertSame(50000.0, $hasil['potongan']);
        $this->assertSame(50000.0, $hasil['nominal']);
    }
}
