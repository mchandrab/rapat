<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_scanner', function (Blueprint $table): void {
            $table->dropForeign(['scanner_user_id']);
            $table->foreign('scanner_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kas_scanner', function (Blueprint $table): void {
            $table->dropForeign(['scanner_user_id']);
            $table->foreign('scanner_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
