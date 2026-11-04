<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-009 / D-014: satu kas awal per (rapat, akun Scanner); tidak ada top-up atau transfer.
        Schema::create('kas_scanner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->foreignId('scanner_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('kas_awal', 15, 2);
            $table->foreignId('diserahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diserahkan_pada')->nullable();

            // O-006: konfirmasi penerimaan oleh Scanner belum diputuskan; kolom disiapkan tetapi belum dipakai.
            $table->timestamp('diterima_pada')->nullable();

            $table->decimal('sisa_fisik', 15, 2)->nullable();
            $table->text('catatan_selisih')->nullable();
            $table->enum('status', ['menunggu', 'aktif', 'ditutup'])->default('aktif');
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditutup_pada')->nullable();
            $table->timestamps();

            $table->unique(['rapat_id', 'scanner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_scanner');
    }
};
