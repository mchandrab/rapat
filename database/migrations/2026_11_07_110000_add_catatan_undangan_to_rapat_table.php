<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->text('catatan_undangan')->nullable()->after('alamat_lokasi');
        });
    }

    public function down(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->dropColumn('catatan_undangan');
        });
    }
};
