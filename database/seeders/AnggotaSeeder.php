<?php

namespace Database\Seeders;

use App\Models\Anggota;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data contoh opsional untuk uji coba. Tidak dipanggil DatabaseSeeder;
 * jalankan manual dengan `php artisan db:seed --class=AnggotaSeeder`.
 */
class AnggotaSeeder extends Seeder
{
    public function run(): void
    {
        $contoh = [
            ['nba' => '020006000010339', 'nama' => 'Budi Santoso', 'wa' => '081234567890', 'tgl_ja' => '15/11/2026', 'simpanan' => 50000, 'kolektibilitas' => 1, 'pinjaman_lancar' => true, 'status' => 'Aktif', 'jenis' => 'Biasa'],
            ['nba' => '020006000010340', 'nama' => 'Siti Rahma', 'wa' => '081234567891', 'tgl_ja' => '04/01/2022', 'simpanan' => 450000, 'kolektibilitas' => 2, 'pinjaman_lancar' => true, 'status' => 'Aktif', 'jenis' => 'Biasa'],
            ['nba' => '020006000010341', 'nama' => 'Andi Pratama', 'wa' => '081234567892', 'tgl_ja' => '10/03/2024', 'simpanan' => 200000, 'kolektibilitas' => 4, 'pinjaman_lancar' => false, 'status' => 'Aktif', 'jenis' => 'Biasa'],
            ['nba' => '020006000010342', 'nama' => 'Rina Marlina', 'wa' => '081234567893', 'tgl_ja' => '09/08/2020', 'simpanan' => 600000, 'kolektibilitas' => 1, 'pinjaman_lancar' => true, 'status' => 'Nonaktif', 'jenis' => 'Biasa'],
            ['nba' => '020006000010343', 'nama' => 'Dewi Anggraini', 'wa' => '081234567894', 'tgl_ja' => '18/10/2026', 'simpanan' => 75000, 'kolektibilitas' => 2, 'pinjaman_lancar' => true, 'status' => 'Aktif', 'jenis' => 'Biasa'],
            ['nba' => '020006000010344', 'nama' => 'Hendra Wijaya', 'wa' => '081234567895', 'tgl_ja' => '21/02/2019', 'simpanan' => 900000, 'kolektibilitas' => 1, 'pinjaman_lancar' => true, 'status' => 'Aktif', 'jenis' => 'Luar Biasa'],
        ];

        foreach ($contoh as $row) {
            Anggota::updateOrCreate(['no_anggota' => $row['nba']], [
                'nama' => $row['nama'],
                'no_hp' => $row['wa'],
                'jenis_anggota' => $row['jenis'] === 'Luar Biasa' ? 'luar_biasa' : 'biasa',
                'status_anggota' => $row['status'] === 'Aktif' ? 'aktif' : 'nonaktif',
                'tgl_ja' => Carbon::createFromFormat('d/m/Y', $row['tgl_ja'])->format('Y-m-d'),
                'simpanan_wajib' => $row['simpanan'],
                'kolektibilitas' => $row['kolektibilitas'],
                'pinjaman_lancar' => $row['pinjaman_lancar'],
            ]);
        }
    }
}
