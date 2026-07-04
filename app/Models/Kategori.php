<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'kategori';

    protected $fillable = ['nama', 'pakai_desain'];

    protected function casts(): array
    {
        return [
            'pakai_desain' => 'boolean',
        ];
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
