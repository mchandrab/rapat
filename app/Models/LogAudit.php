<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * BR-012: log audit tidak boleh diubah atau dihapus.
 */
class LogAudit extends Model
{
    public $timestamps = false;

    protected $table = 'log_audit';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('log_audit bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('log_audit bersifat append-only.'));
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktor_user_id');
    }

    public static function catat(string $aksi, string $entitas, int $entitasId, ?array $sebelum, ?array $sesudah, ?string $alasan = null): self
    {
        return static::create([
            'aktor_user_id' => auth()->id(),
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip_address' => request()?->ip(),
            'alasan' => $alasan,
        ]);
    }
}
