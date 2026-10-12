<?php

namespace App\Models\Kanban;

use App\Models\User;
use App\Support\Kanban\Gembok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kunci pada board, list, atau kartu. Lihat {@see Gembok}
 * untuk aturan mana yang menang kalau bertingkat.
 */
class Kunci extends Model
{
    public const JENIS = ['board', 'kolom', 'kartu'];

    protected $table = 'kanban_kunci';

    public $timestamps = false;

    protected $fillable = ['jenis', 'target_id', 'user_id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        $lupakan = fn () => Gembok::ini()->lupakan();
        static::created($lupakan);
        static::updated($lupakan);
        static::deleted($lupakan);
    }

    /** Siapa yang menguncinya. */
    public function pengunci(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Daftar yang boleh mengubah: baris berisi user_id ATAU peran. */
    public function akses(): HasMany
    {
        return $this->hasMany(KunciAkses::class, 'kunci_id');
    }

    /** Kalimat singkat untuk ditampilkan, mis. "Rudi, Editor". */
    public function ringkasAkses(): string
    {
        $isi = $this->akses
            ->map(fn (KunciAkses $a) => $a->user_id
                ? ($a->pengguna?->nama ?? $a->pengguna?->name)
                : ($a->peran ? ucfirst(str_replace('_', ' ', $a->peran)) : null))
            ->filter()
            ->all();

        return $isi ? implode(', ', $isi) : 'central admins';
    }
}
