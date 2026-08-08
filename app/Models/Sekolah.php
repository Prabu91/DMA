<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ScopedBy(CabangScope::class)]
class Sekolah extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'sekolah';

    protected $fillable = [
        'id_sekolah',
        'nama',
        'alamat',
        'kota',
        'pic_sekolah',
        'no_telp_pic',
        'email_guru',
        'maps_link',
        'cabang_id',
        // Storefront (login mandiri sekolah)
        'email',
        'google_id', // future-proof Socialite — belum dibangun
        'avatar',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Auto-generate id_sekolah unik (kode akun) — GLOBAL: SKL-000001.
     * Sengaja tidak per-cabang karena registrasi mandiri bisa tanpa cabang
     * (kota "lainnya"). Format placeholder, mudah diubah di satu tempat ini.
     * withoutGlobalScopes agar sekuens akurat lintas konteks.
     */
    public static function generateIdSekolah(): string
    {
        $prefix = 'SKL-';

        $max = static::withoutGlobalScopes()
            ->where('id_sekolah', 'like', $prefix.'%')
            ->pluck('id_sekolah')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
