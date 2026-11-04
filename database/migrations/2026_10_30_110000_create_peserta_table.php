<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-012: hasil RAT menyimpan snapshot agar koreksi master tidak mengubah keputusan lama.
        Schema::create('peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->foreignId('anggota_id')->constrained('anggota')->cascadeOnDelete();
            $table->enum('hasil_kelayakan', ['layak', 'tidak_layak']);
            $table->enum('status_verifikasi', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('alasan_override')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->decimal('snapshot_simpanan_wajib', 15, 2)->default(0);
            $table->date('snapshot_tgl_ja')->nullable();
            $table->decimal('snapshot_minimum_proporsional', 15, 2)->nullable();
            $table->unsignedTinyInteger('snapshot_kolektibilitas')->nullable();
            $table->boolean('snapshot_pinjaman_lancar')->nullable();
            $table->json('snapshot_konfigurasi');

            // BR-004: satu token acak per (rapat, anggota). Hash untuk lookup, ciphertext untuk cetak ulang.
            $table->char('qr_token_hash', 64)->nullable()->unique();
            $table->text('qr_token_ciphertext')->nullable();
            $table->timestamp('qr_dibuat_at')->nullable();

            $table->timestamps();

            $table->unique(['rapat_id', 'anggota_id']);
            $table->index(['rapat_id', 'status_verifikasi']);
            $table->index(['rapat_id', 'hasil_kelayakan']);
        });

        // BR-005: setiap percobaan kirim/cetak undangan tercatat beserta hasilnya.
        Schema::create('log_undangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->foreignId('peserta_id')->constrained('peserta')->cascadeOnDelete();
            $table->foreignId('petugas_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('metode', ['wa', 'cetak']);
            $table->string('status', 30);
            $table->timestamp('waktu_aksi');
            $table->string('provider_message_id', 100)->nullable();
            $table->text('detail_error')->nullable();
            $table->timestamps();

            $table->index(['rapat_id', 'waktu_aksi']);
            $table->index('petugas_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_undangan');
        Schema::dropIfExists('peserta');
    }
};
