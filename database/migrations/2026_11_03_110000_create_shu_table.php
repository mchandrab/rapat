<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-011: SHU per anggota per tahun buku, dikelola Admin.
        Schema::create('shu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggota_id')->constrained('anggota')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun_buku');
            $table->decimal('balas_jasa_saham', 15, 2)->default(0);
            $table->decimal('balas_jasa_pinjaman', 15, 2)->default(0);
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['anggota_id', 'tahun_buku']);
            $table->index('tahun_buku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu');
    }
};
