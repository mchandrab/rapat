<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-011: tombol Admin membuka/menutup cek SHU publik per tahun buku,
        // tidak terikat status rapat.
        Schema::create('shu_publik', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_buku')->unique();
            $table->boolean('aktif')->default(false);
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_at')->nullable();
            $table->timestamp('ditutup_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_publik');
    }
};
