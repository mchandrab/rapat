<?php

namespace App\Services;

use App\Models\DoorprizeHadiah;
use App\Models\DoorprizePemenang;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** D-044: master hadiah, gerbang undian, dan pengisian stok atomik. */
final class Doorprize
{
    public function tambahHadiah(Rapat $rapat, array $data): DoorprizeHadiah
    {
        return DB::transaction(function () use ($rapat, $data): DoorprizeHadiah {
            $hadiah = $rapat->doorprizeHadiah()->create($data);
            LogAudit::catat('buat', 'doorprize_hadiah', $hadiah->id, null, $hadiah->only(['rapat_id', 'nama', 'jumlah', 'keterangan']));

            return $hadiah;
        });
    }

    public function ubahHadiah(DoorprizeHadiah $hadiah, array $data): DoorprizeHadiah
    {
        return DB::transaction(function () use ($hadiah, $data): DoorprizeHadiah {
            $hadiah = DoorprizeHadiah::query()->lockForUpdate()->findOrFail($hadiah->id);
            $terisi = $hadiah->jumlahPemenang();

            if ((int) $data['jumlah'] < $terisi) {
                throw ValidationException::withMessages([
                    'jumlah' => "Jumlah hadiah tidak boleh lebih kecil dari {$terisi} pemenang yang sudah tercatat.",
                ]);
            }

            $sebelum = $hadiah->only(['nama', 'jumlah', 'keterangan']);
            $hadiah->update($data);
            LogAudit::catat('ubah', 'doorprize_hadiah', $hadiah->id, $sebelum, $hadiah->only(['nama', 'jumlah', 'keterangan']));

            return $hadiah;
        });
    }

    public function hapusHadiah(DoorprizeHadiah $hadiah): void
    {
        DB::transaction(function () use ($hadiah): void {
            $hadiah = DoorprizeHadiah::query()->lockForUpdate()->findOrFail($hadiah->id);

            if ($hadiah->hasil()->exists()) {
                throw ValidationException::withMessages([
                    'hadiah' => 'Hadiah tidak dapat dihapus karena sudah memiliki hasil undian.',
                ]);
            }

            $sebelum = $hadiah->only(['rapat_id', 'nama', 'jumlah', 'keterangan']);
            $id = $hadiah->id;
            $hadiah->delete();
            LogAudit::catat('hapus', 'doorprize_hadiah', $id, $sebelum, null);
        });
    }

    /** Peserta hadir pada rapat ini yang belum pernah keluar di undian. */
    public function kandidat(Rapat $rapat): Builder
    {
        return Peserta::query()
            ->where('peserta.rapat_id', $rapat->id)
            ->whereIn('peserta.id', Presensi::query()->select('peserta_id'))
            ->whereNotIn('peserta.id', DoorprizePemenang::query()
                ->where('rapat_id', $rapat->id)
                ->select('peserta_id'));
    }

    public function undi(Rapat $rapat, DoorprizeHadiah $hadiah): ?Peserta
    {
        $this->pastikanUndianDiizinkan($rapat);
        $this->pastikanHadiahTersedia($rapat, $hadiah);

        return $this->kandidat($rapat)->inRandomOrder()->first();
    }

    /** $masihAda=true mencatat pemenang; false mencatat peserta gugur tanpa memakai stok. */
    public function putuskan(Rapat $rapat, Peserta $peserta, DoorprizeHadiah $hadiah, bool $masihAda): DoorprizePemenang
    {
        return DB::transaction(function () use ($rapat, $peserta, $hadiah, $masihAda): DoorprizePemenang {
            $rapat = Rapat::query()->lockForUpdate()->findOrFail($rapat->id);
            $hadiah = DoorprizeHadiah::query()->lockForUpdate()->findOrFail($hadiah->id);
            $this->pastikanUndianDiizinkan($rapat);
            if ($hadiah->rapat_id !== $rapat->id) {
                throw ValidationException::withMessages(['hadiah' => 'Hadiah bukan milik rapat ini.']);
            }
            if ($masihAda && $hadiah->jumlahPemenang() >= $hadiah->jumlah) {
                throw ValidationException::withMessages(['hadiah' => 'Hadiah yang dipilih sudah habis.']);
            }

            $peserta = Peserta::query()->lockForUpdate()->findOrFail($peserta->id);
            if ($peserta->rapat_id !== $rapat->id || ! Presensi::query()->where('peserta_id', $peserta->id)->exists()) {
                throw ValidationException::withMessages(['doorprize' => 'Peserta bukan kandidat hadir pada rapat ini.']);
            }
            if (DoorprizePemenang::query()->where('rapat_id', $rapat->id)->where('peserta_id', $peserta->id)->exists()) {
                throw ValidationException::withMessages(['doorprize' => 'Peserta sudah memiliki hasil doorprize.']);
            }

            $baris = DoorprizePemenang::create([
                'rapat_id' => $rapat->id,
                'peserta_id' => $peserta->id,
                'doorprize_hadiah_id' => $masihAda ? $hadiah->id : null,
                'nama_hadiah_snapshot' => $masihAda ? $hadiah->nama : null,
                'hasil' => $masihAda ? DoorprizePemenang::MENANG : DoorprizePemenang::GUGUR,
                'dicatat_oleh' => auth()->id(),
            ]);

            LogAudit::catat('doorprize', 'doorprize_pemenang', $baris->id, null, [
                'rapat_id' => $rapat->id,
                'peserta_id' => $peserta->id,
                'doorprize_hadiah_id' => $baris->doorprize_hadiah_id,
                'nama_hadiah_snapshot' => $baris->nama_hadiah_snapshot,
                'hasil' => $baris->hasil,
            ], $masihAda ? 'masih ada' : 'sudah pulang');

            return $baris;
        });
    }

    public function pastikanUndianDiizinkan(Rapat $rapat): void
    {
        $boleh = $rapat->status === Rapat::BERLANGSUNG
            || ($rapat->status === Rapat::VERIFIKASI && $rapat->presensi_dibuka_at !== null);

        if (! $boleh) {
            throw ValidationException::withMessages([
                'doorprize' => 'Undian hanya dapat dilakukan saat Verifikasi setelah presensi dibuka atau saat rapat Berlangsung.',
            ]);
        }
    }

    private function pastikanHadiahTersedia(Rapat $rapat, DoorprizeHadiah $hadiah): void
    {
        if ($hadiah->rapat_id !== $rapat->id) {
            throw ValidationException::withMessages(['hadiah' => 'Hadiah bukan milik rapat ini.']);
        }

        if ($hadiah->jumlahPemenang() >= $hadiah->jumlah) {
            throw ValidationException::withMessages(['hadiah' => 'Hadiah yang dipilih sudah habis.']);
        }
    }
}
