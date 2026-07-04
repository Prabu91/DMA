<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paket extends Model
{
    protected $table = 'paket';

    protected $fillable = ['nama', 'deskripsi', 'harga', 'status'];

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'paket_produk', 'paket_id', 'produk_id');
    }

    public function aturanFreeSekolah(): HasMany
    {
        return $this->hasMany(AturanFreeSekolah::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
