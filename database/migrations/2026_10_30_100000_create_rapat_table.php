<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-002: satu rapat menyimpan seluruh parameter yang dipakai modul lain.
        Schema::create('rapat', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['rat', 'biasa'])->default('rat');
            $table->string('judul', 150);
            $table->unsignedSmallInteger('tahun_buku');
            $table->date('tanggal_acuan_saldo');
            $table->date('tgl_rapat');
            // Jam disimpan HH:MM agar sama persis dengan input type="time" di semua database.
            $table->char('jam_mulai_presensi', 5);
            $table->char('jam_mulai_rat', 5);
            $table->char('jam_selesai', 5)->nullable();
            $table->string('lokasi', 150);
            $table->enum('status', ['draf', 'verifikasi', 'berlangsung', 'selesai', 'dibatalkan'])->default('draf');
            // O-005: bendera presensi terpisah dari status rapat belum diaktifkan.
            $table->timestamp('presensi_dibuka_at')->nullable();
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('mode_presensi', ['1x', '2x'])->default('1x');
            $table->enum('mode_kelayakan', ['A', 'B'])->default('A');
            $table->decimal('minimal_simpanan_tahunan', 15, 2)->default(0);
            $table->decimal('simpanan_per_bulan', 15, 2)->default(0);
            $table->boolean('gunakan_proporsional')->default(true);
            $table->unsignedTinyInteger('batas_kolektibilitas')->default(2);
            $table->boolean('wajib_pinjaman_lancar')->default(false);
            $table->enum('basis_kuorum', ['semua_biasa', 'terverifikasi'])->default('semua_biasa');
            $table->unsignedTinyInteger('persen_kuorum')->default(50);
            $table->unsignedInteger('tambahan_kuorum')->default(0);
            $table->decimal('transport_dasar', 15, 2)->default(0);
            $table->unsignedSmallInteger('toleransi_menit')->default(0);
            // O-003 dan O-004 belum diputuskan: kolom disiapkan, nilai tetap kosong.
            $table->unsignedSmallInteger('batas_hangus_menit')->nullable();
            $table->decimal('batas_peringatan_kas', 15, 2)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['tahun_buku', 'status']);
        });

        // DATABASE_SCHEMA: rentang tier tidak boleh tumpang tindih.
        Schema::create('rapat_transport_tier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->unsignedSmallInteger('menit_mulai');
            $table->unsignedSmallInteger('menit_akhir');
            $table->decimal('potongan', 15, 2);
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();

            $table->unique(['rapat_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapat_transport_tier');
        Schema::dropIfExists('rapat');
    }
};
