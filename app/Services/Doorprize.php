<?php

namespace App\Services;

use App\Models\DoorprizePemenang;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * BR-011: undian doorprize hanya untuk peserta hadir, satu peserta satu hasil
 * per rapat. Peserta yang sudah menang atau dinyatakan pulang tidak diundi lagi.
 */
final class Doorprize
{
    public function target(Rapat $rapat): int
    {
        return (int) $rapat->jumlah_pemenang_doorprize;
    }

    public function jumlahMenang(Rapat $rapat): int
    {
        return DoorprizePemenang::query()
            ->where('rapat_id', $rapat->id)
            ->where('hasil', DoorprizePemenang::MENANG)
            ->count();
    }

    public function sisa(Rapat $rapat): int
    {
        return max(0, $this->target($rapat) - $this->jumlahMenang($rapat));
    }

    /** Peserta hadir pada rapat ini yang belum pernah keluar di undian. */
    public function kandidat(Rapat $rapat): Builder
    {
        return Peserta::query()
            ->where('peserta.rapat_id', $rapat->id)
            ->whereIn('peserta.id', Presensi::query()->select('peserta_id'))
            ->whereNotIn('peserta.id', DoorprizePemenang::query()
                ->where('rapat_id', $rapat->id)
                ->select('peserta_id'));
    }

    /** Satu kandidat acak, atau null bila tidak ada yang bisa diundi. */
    public function undi(Rapat $rapat): ?Peserta
    {
        return $this->kandidat($rapat)->inRandomOrder()->first();
    }

    /**
     * $masihAda = true berarti peserta menang; false berarti sudah pulang dan gugur.
     */
    public function putuskan(Rapat $rapat, Peserta $peserta, bool $masihAda): DoorprizePemenang
    {
        return DB::transaction(function () use ($rapat, $peserta, $masihAda): DoorprizePemenang {
            $urutan = $masihAda ? $this->jumlahMenang($rapat) + 1 : null;

            $baris = DoorprizePemenang::create([
                'rapat_id' => $rapat->id,
                'peserta_id' => $peserta->id,
                'hasil' => $masihAda ? DoorprizePemenang::MENANG : DoorprizePemenang::GUGUR,
                'urutan' => $urutan,
                'dicatat_oleh' => auth()->id(),
            ]);

            LogAudit::catat('doorprize', 'doorprize_pemenang', $baris->id, null, [
                'rapat_id' => $rapat->id,
                'peserta_id' => $peserta->id,
                'hasil' => $baris->hasil,
                'urutan' => $urutan,
            ], $masihAda ? 'masih ada' : 'sudah pulang');

            return $baris;
        });
    }
}
