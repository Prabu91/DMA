<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukBonus extends Model
{
    protected $table = 'produk_bonus';

    protected $fillable = ['produk_id', 'bonus_produk_id', 'qty'];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function bonusProduk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'bonus_produk_id');
    }
}
