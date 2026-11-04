<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-INT-01 / D-019: satu baris per layanan, secret disimpan sebagai ciphertext.
        Schema::create('pengaturan_integrasi', function (Blueprint $table) {
            $table->id();
            $table->enum('layanan', ['captcha', 'fonnte'])->unique();
            $table->boolean('aktif')->default(false);
            $table->string('provider', 50)->nullable();
            $table->string('site_key')->nullable();
            $table->string('label')->nullable();
            $table->text('secret')->nullable();
            $table->unsignedInteger('secret_versi')->default(0);
            $table->string('status_uji', 20)->nullable();
            $table->text('status_uji_pesan')->nullable();
            $table->timestamp('status_uji_pada')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_integrasi');
    }
};
