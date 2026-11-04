<?php

namespace Database\Seeders;

use App\Models\Rapat;
use Illuminate\Database\Seeder;

class RapatSeeder extends Seeder
{
    public function run(): void
    {
        $rapat = Rapat::updateOrCreate(
            ['tahun_acuan' => 2026, 'jenis' => 'rat'],
            [
                'judul' => 'Rapat Anggota Tahunan 2026',
                'tanggal_acuan_saldo' => '2026-12-31',
                'tgl_rapat' => '2026-11-15',
                'jam_mulai_presensi' => '07:00',
                'jam_mulai_rat' => '08:00',
                'jam_selesai' => null,
                'lokasi' => 'Aula Koperasi Kredit Rukun',
                'status' => Rapat::DRAF,
                'mode_presensi' => '1x',
                'mode_kelayakan' => 'A',
                'minimal_simpanan_tahunan' => 300000,
                'simpanan_per_bulan' => 25000,
                'gunakan_proporsional' => true,
                'batas_kolektibilitas' => 2,
                'wajib_pinjaman_lancar' => false,
                'basis_kuorum' => 'semua_biasa',
                'persen_kuorum' => 50,
                'tambahan_kuorum' => 1,
                'transport_dasar' => 100000,
                'toleransi_menit' => 60,
            ],
        );

        $tier = [
            [1, 30, 25000],
            [31, 60, 50000],
            [61, 120, 75000],
        ];

        $rapat->tier()->delete();

        foreach ($tier as $urutan => [$mulai, $akhir, $potongan]) {
            $rapat->tier()->create([
                'menit_mulai' => $mulai,
                'menit_akhir' => $akhir,
                'potongan' => $potongan,
                'urutan' => $urutan + 1,
            ]);
        }
    }
}
