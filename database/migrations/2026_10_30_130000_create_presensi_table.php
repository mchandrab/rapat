<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-006: satu presensi per peserta, mencegah presensi ganda.
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_id')->unique()->constrained('peserta')->cascadeOnDelete();
            $table->timestamp('datang_at');
            // O-001: mode 2x belum diputuskan, kolom pulang disiapkan tetapi belum dipakai.
            $table->timestamp('pulang_at')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            // Foto & TTE privat: diisi pada tahap Fase 5 berikutnya.
            $table->string('foto_path')->nullable();
            $table->string('tte_path')->nullable();
            $table->foreignId('tier_id')->nullable()->constrained('rapat_transport_tier')->nullOnDelete();
            $table->decimal('nominal_transport_final', 15, 2)->default(0);
            $table->string('kode_resi', 30)->nullable()->unique();
            // O-002: offline belum diputuskan; semua presensi saat ini tersinkron.
            $table->string('status_sinkron', 20)->default('tersinkron');
            $table->timestamps();
        });

        // BR-009: hanya dibuat bila ada uang yang benar-benar diserahkan (nominal > 0).
        Schema::create('transport_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_id')->unique()->constrained('peserta')->cascadeOnDelete();
            $table->foreignId('kas_scanner_id')->constrained('kas_scanner');
            $table->foreignId('dibayar_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->timestamp('dibayar_pada');
            $table->string('kode_resi', 30)->unique();
            $table->timestamps();

            $table->index('kas_scanner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_pembayaran');
        Schema::dropIfExists('presensi');
    }
};
