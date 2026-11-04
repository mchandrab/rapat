<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-001/BR-002: master anggota, tanpa data verifikasi per rapat (D-017).
        Schema::create('anggota', function (Blueprint $table) {
            $table->id();
            $table->string('no_anggota', 15)->unique();
            $table->string('nama', 100);
            $table->string('no_hp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('alamat')->nullable();
            $table->enum('jenis_anggota', ['biasa', 'luar_biasa'])->default('biasa');
            $table->enum('status_anggota', ['aktif', 'nonaktif'])->default('aktif');
            $table->date('tgl_ja');
            $table->date('tgl_keluar')->nullable();
            $table->decimal('simpanan_wajib', 15, 2)->default(0);
            $table->unsignedTinyInteger('kolektibilitas')->default(1);
            $table->boolean('pinjaman_lancar')->default(true);
            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota');
    }
};
