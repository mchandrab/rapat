<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-011: jumlah pemenang adalah parameter rapat, bukan angka di kode.
        Schema::table('rapat', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pemenang_doorprize')->default(10);
        });

        // Peserta yang sudah keluar (menang/gugur) tidak diundi lagi pada rapat yang sama.
        Schema::create('doorprize_pemenang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->foreignId('peserta_id')->constrained('peserta')->cascadeOnDelete();
            $table->enum('hasil', ['menang', 'gugur']);
            $table->unsignedSmallInteger('urutan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rapat_id', 'peserta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doorprize_pemenang');

        Schema::table('rapat', function (Blueprint $table) {
            $table->dropColumn('jumlah_pemenang_doorprize');
        });
    }
};
