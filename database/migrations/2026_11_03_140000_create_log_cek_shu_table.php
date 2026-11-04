<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-SHU-LOG-01: log pencarian publik, retensi 14 hari, tanpa nominal SHU.
        Schema::create('log_cek_shu', function (Blueprint $table) {
            $table->id();
            $table->string('nba_dicari', 15)->nullable();
            $table->unsignedSmallInteger('tahun_buku')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('hasil', ['ditemukan', 'tidak_ditemukan', 'ditolak_captcha', 'dibatasi', 'tidak_valid']);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_cek_shu');
    }
};
