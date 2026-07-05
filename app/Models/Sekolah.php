<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[ScopedBy(CabangScope::class)]
class Sekolah extends Authenticatable
{
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
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
     * Auto-generate id_sekolah unik per cabang: SKL-{KODEAREA}-0001.
     * Fallback ke C{id} bila cabang tak punya kode_area.
     * Memakai withoutGlobalScopes agar sekuens akurat lintas konteks user.
     */
    public static function generateIdSekolah(Cabang $cabang): string
    {
        $area = $cabang->kode_area ?: ('C'.$cabang->id);
        $prefix = 'SKL-'.strtoupper($area).'-';

        $max = static::withoutGlobalScopes()
            ->where('id_sekolah', 'like', $prefix.'%')
            ->pluck('id_sekolah')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
