<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-012: jejak perubahan master anggota, append-only.
        Schema::create('log_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aktor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 30);
            $table->string('entitas', 50);
            $table->unsignedBigInteger('entitas_id');
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('alasan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitas', 'entitas_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_audit');
    }
};
