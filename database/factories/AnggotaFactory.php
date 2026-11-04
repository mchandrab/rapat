<?php

namespace Database\Factories;

use App\Models\Anggota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anggota>
 */
class AnggotaFactory extends Factory
{
    protected $model = Anggota::class;

    public function definition(): array
    {
        return [
            // Blok ke-3 = 020 agar tidak bentrok dengan nomor contoh AnggotaSeeder (blok 010).
            'no_anggota' => fake()->unique()->numerify('02000600002####'),
            'nama' => fake()->name(),
            'no_hp' => fake()->numerify('08##########'),
            'email' => null,
            'alamat' => null,
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2020-01-01',
            'tgl_keluar' => null,
            'simpanan_wajib' => 300000,
            'kolektibilitas' => 1,
            'pinjaman_lancar' => true,
        ];
    }
}
