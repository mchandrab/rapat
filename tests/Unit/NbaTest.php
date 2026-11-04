<?php

namespace Tests\Unit;

use App\Support\Nba;
use PHPUnit\Framework\TestCase;

class NbaTest extends TestCase
{
    public function test_menyimpan_dan_menampilkan_nba_15_digit(): void
    {
        $this->assertSame('020006000010339', Nba::normalize('020006000010339'));
        $this->assertSame('020006.000.010.339', Nba::format('020006000010339'));
    }

    public function test_menerima_input_bertitik_atau_berspasi(): void
    {
        $this->assertSame('020006000010339', Nba::normalize('020006.000.010.339'));
        $this->assertSame('020006000010339', Nba::normalize(' 020006 000 010 339 '));
    }

    public function test_menolak_panjang_atau_karakter_yang_salah(): void
    {
        $this->assertNull(Nba::normalize('02000600001033'));
        $this->assertNull(Nba::normalize('0200060000103399'));
        $this->assertNull(Nba::normalize('02000600001033A'));
        $this->assertNull(Nba::normalize(null));
    }

    public function test_nilai_tidak_valid_ditampilkan_apa_adanya(): void
    {
        $this->assertSame('123', Nba::format('123'));
    }
}
