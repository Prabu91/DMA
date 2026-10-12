<?php

namespace App\Models\Kanban;

use App\Models\User;
use App\Support\Kanban\Gembok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris daftar akses kunci: orang tertentu ATAU satu peran. */
class KunciAkses extends Model
{
    protected $table = 'kanban_kunci_akses';

    public $timestamps = false;

    protected $fillable = ['kunci_id', 'user_id', 'peran'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        $lupakan = fn () => Gembok::ini()->lupakan();
        static::created($lupakan);
        static::updated($lupakan);
        static::deleted($lupakan);
    }

    public function kunci(): BelongsTo
    {
        return $this->belongsTo(Kunci::class, 'kunci_id');
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
