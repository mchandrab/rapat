<?php

namespace App\Console\Commands;

use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Services\Kuorum;
use App\Services\Laporan;
use Illuminate\Console\Command;

/**
 * Fase 10: bukti waktu hitung pada data nyata (bukan data uji). Hanya membaca.
 * Target PRD: papan kuorum <= 10 detik.
 */
class Benchmark extends Command
{
    protected $signature = 'erat:benchmark';

    protected $description = 'Ukur waktu hitung kuorum dan laporan pada data rapat terakhir';

    public function handle(): int
    {
        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            $this->error('Belum ada rapat untuk diukur.');

            return self::FAILURE;
        }

        $kuorum = $this->ukur(fn () => (new Kuorum)->hitung($rapat));
        $laporan = $this->ukur(fn () => (new Laporan)->rekap($rapat, Laporan::SEMUA_BAGIAN));

        $this->table(['Langkah', 'Detik', 'Target'], [
            ['Kuorum', number_format($kuorum, 3, ',', '.'), 'maksimal 10 detik'],
            ['Laporan semua bagian', number_format($laporan, 3, ',', '.'), 'tanpa target'],
        ]);

        $this->line('Data diukur: '.$rapat->judul
            .' - peserta '.Peserta::query()->where('rapat_id', $rapat->id)->count()
            .', presensi '.Presensi::query()->whereHas('peserta', fn ($q) => $q->where('rapat_id', $rapat->id))->count());

        if ($kuorum > 10) {
            $this->warn('Kuorum melewati target 10 detik; periksa indeks dan jumlah peserta.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ukur(callable $kerja): float
    {
        $mulai = microtime(true);
        $kerja();

        return microtime(true) - $mulai;
    }
}
