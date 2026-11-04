<?php

namespace App\Services;

use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use Illuminate\Support\Carbon;

/**
 * BR-010: kuorum hanya menghitung anggota jenis `biasa`. Target =
 * floor(persen × basis populasi) + tambahan, seluruhnya dari konfigurasi rapat.
 */
final class Kuorum
{
    /**
     * @return array{populasi: int, target: int, hadir: int, hadir_luar_biasa: int, persen: float, tercapai: bool, kekurangan: int, jam_tercapai: ?Carbon}
     */
    public function hitung(Rapat $rapat): array
    {
        $populasi = $this->dasar($rapat)->count();

        $target = intdiv((int) $rapat->persen_kuorum * $populasi, 100) + (int) $rapat->tambahan_kuorum;

        $hadir = $this->hadir($rapat)->count();

        $hadirLuarBiasa = Presensi::query()
            ->join('peserta', 'peserta.id', '=', 'presensi.peserta_id')
            ->join('anggota', 'anggota.id', '=', 'peserta.anggota_id')
            ->where('peserta.rapat_id', $rapat->id)
            ->where('anggota.jenis_anggota', 'luar_biasa')
            ->count();

        // Rapat tanpa peserta tidak dianggap sah meski targetnya kebetulan nol.
        $tercapai = $target > 0 && $hadir >= $target;

        $jamTercapai = $tercapai
            ? $this->hadir($rapat)->orderBy('presensi.datang_at')->skip($target - 1)->value('presensi.datang_at')
            : null;

        return [
            'populasi' => $populasi,
            'target' => $target,
            'hadir' => $hadir,
            'hadir_luar_biasa' => $hadirLuarBiasa,
            'persen' => $populasi === 0 ? 0.0 : round($hadir / $populasi * 100, 1),
            'tercapai' => $tercapai,
            'kekurangan' => max(0, $target - $hadir),
            'jam_tercapai' => $jamTercapai === null ? null : Carbon::parse($jamTercapai),
        ];
    }

    /** Populasi: peserta rapat berjenis `biasa` dan masih aktif; basis `terverifikasi` menambah saringan Verified. */
    private function dasar(Rapat $rapat)
    {
        return Peserta::query()
            ->where('peserta.rapat_id', $rapat->id)
            ->join('anggota', 'anggota.id', '=', 'peserta.anggota_id')
            ->where('anggota.jenis_anggota', 'biasa')
            ->where('anggota.status_anggota', 'aktif')
            ->when(
                $rapat->basis_kuorum === 'terverifikasi',
                fn ($q) => $q->where('peserta.status_verifikasi', Peserta::VERIFIED),
            );
    }

    /** Pembilang: presensi rapat ini dari anggota biasa yang masih aktif. */
    private function hadir(Rapat $rapat)
    {
        return Presensi::query()
            ->join('peserta', 'peserta.id', '=', 'presensi.peserta_id')
            ->join('anggota', 'anggota.id', '=', 'peserta.anggota_id')
            ->where('peserta.rapat_id', $rapat->id)
            ->where('anggota.jenis_anggota', 'biasa')
            ->where('anggota.status_anggota', 'aktif');
    }
}
