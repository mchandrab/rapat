<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-011: hasil undian doorprize. Baris `gugur` tetap disimpan agar peserta
 * yang sudah pulang tidak muncul lagi di undian berikutnya.
 */
class DoorprizePemenang extends Model
{
    public const MENANG = 'menang';

    public const GUGUR = 'gugur';

    protected $table = 'doorprize_pemenang';

    protected $fillable = ['rapat_id', 'peserta_id', 'hasil', 'urutan', 'dicatat_oleh'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }
}
