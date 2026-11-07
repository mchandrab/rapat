<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->dropIndex('rapat_tahun_buku_status_index');
            $table->dropColumn('tahun_acuan');
        });
    }

    public function down(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_acuan')->nullable()->after('judul');
        });

        DB::table('rapat')->orderBy('id')->eachById(function ($rapat): void {
            DB::table('rapat')->where('id', $rapat->id)->update([
                'tahun_acuan' => (int) substr($rapat->tanggal_acuan_saldo, 0, 4),
            ]);
        });

        Schema::table('rapat', function (Blueprint $table) {
            $table->index(['tahun_acuan', 'status']);
        });
    }
};
