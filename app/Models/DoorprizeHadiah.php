<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoorprizeHadiah extends Model
{
    protected $table = 'doorprize_hadiah';

    protected $fillable = ['rapat_id', 'nama', 'jumlah', 'keterangan'];

    protected function casts(): array
    {
        return ['jumlah' => 'integer'];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class);
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(DoorprizePemenang::class);
    }

    public function jumlahPemenang(): int
    {
        return $this->hasil()->where('hasil', DoorprizePemenang::MENANG)->count();
    }

    public function sisa(): int
    {
        return max(0, $this->jumlah - $this->jumlahPemenang());
    }
}
