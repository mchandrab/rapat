<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->string('alamat_lokasi', 500)->nullable()->after('lokasi');
        });
    }

    public function down(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->dropColumn('alamat_lokasi');
        });
    }
};
