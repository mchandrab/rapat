<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doorprize_hadiah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('rapat')->cascadeOnDelete();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('jumlah');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['rapat_id', 'nama']);
        });

        Schema::table('doorprize_pemenang', function (Blueprint $table) {
            $table->foreignId('doorprize_hadiah_id')->nullable()->after('peserta_id')
                ->constrained('doorprize_hadiah')->restrictOnDelete();
            $table->string('nama_hadiah_snapshot', 150)->nullable()->after('doorprize_hadiah_id');
        });

        $waktu = now();
        $rapatIds = DB::table('doorprize_pemenang')
            ->where('hasil', 'menang')
            ->distinct()
            ->pluck('rapat_id');

        foreach ($rapatIds as $rapatId) {
            $jumlah = DB::table('doorprize_pemenang')
                ->where('rapat_id', $rapatId)
                ->where('hasil', 'menang')
                ->count();

            $hadiahId = DB::table('doorprize_hadiah')->insertGetId([
                'rapat_id' => $rapatId,
                'nama' => 'Hadiah arsip (sebelum D-044)',
                'jumlah' => max(1, $jumlah),
                'keterangan' => 'Dibuat otomatis untuk mempertahankan hasil undian lama.',
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);

            DB::table('doorprize_pemenang')
                ->where('rapat_id', $rapatId)
                ->where('hasil', 'menang')
                ->update([
                    'doorprize_hadiah_id' => $hadiahId,
                    'nama_hadiah_snapshot' => 'Hadiah arsip (sebelum D-044)',
                ]);
        }

        Schema::table('doorprize_pemenang', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });

        Schema::table('rapat', function (Blueprint $table) {
            $table->dropColumn('jumlah_pemenang_doorprize');
        });
    }

    public function down(): void
    {
        Schema::table('rapat', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pemenang_doorprize')->default(10);
        });

        Schema::table('doorprize_pemenang', function (Blueprint $table) {
            $table->unsignedSmallInteger('urutan')->nullable();
        });

        foreach (DB::table('doorprize_pemenang')->where('hasil', 'menang')->orderBy('rapat_id')->orderBy('id')->get()->groupBy('rapat_id') as $hasil) {
            foreach ($hasil->values() as $index => $baris) {
                DB::table('doorprize_pemenang')->where('id', $baris->id)->update(['urutan' => $index + 1]);
            }

            DB::table('rapat')->where('id', $hasil->first()->rapat_id)->update([
                'jumlah_pemenang_doorprize' => max(10, $hasil->count()),
            ]);
        }

        Schema::table('doorprize_pemenang', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doorprize_hadiah_id');
            $table->dropColumn('nama_hadiah_snapshot');
        });

        Schema::dropIfExists('doorprize_hadiah');
    }
};
