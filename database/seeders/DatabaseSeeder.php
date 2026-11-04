<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Aplikasi mulai dari database kosong: hanya akun petugas yang dibuat.
     * Data contoh anggota/rapat tetap tersedia lewat seeder terpisah bila diperlukan.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
    }
}
