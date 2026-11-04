<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapatTransportTier extends Model
{
    protected $table = 'rapat_transport_tier';

    protected $fillable = [
        'rapat_id',
        'menit_mulai',
        'menit_akhir',
        'potongan',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'menit_mulai' => 'integer',
            'menit_akhir' => 'integer',
            'urutan' => 'integer',
            'potongan' => 'decimal:2',
        ];
    }
}
