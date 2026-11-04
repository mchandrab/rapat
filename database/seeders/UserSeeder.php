<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Hanya akun Admin awal. Petugas lain dibuat Admin melalui menu Pengguna;
 * data bisnis (anggota, rapat) sengaja tidak diisi.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $petugas = [
            ['name' => 'Budi Admin', 'username' => 'admin', 'role' => 'admin', 'active' => true],
        ];

        foreach ($petugas as $row) {
            User::updateOrCreate(['username' => $row['username']], $row + ['password' => 'password']);
        }
    }
}
