<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'kategori';

    /** Grup laporan finance. */
    public const GRUP = [
        'reguler' => 'Reguler',
        'ob' => 'Openbooth (OB)',
        'yb' => 'Yearbook (YB)',
        'souvenir' => 'Souvenir',
    ];

    protected $fillable = ['nama', 'pakai_desain', 'grup'];

    protected $attributes = [
        'grup' => 'reguler',
    ];

    protected function casts(): array
    {
        return [
            'pakai_desain' => 'boolean',
        ];
    }

    public static function grupLabel(?string $grup): string
    {
        return self::GRUP[$grup] ?? 'Reguler';
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class);
    }

    public function desain(): HasMany
    {
        return $this->hasMany(Desain::class);
    }
}
