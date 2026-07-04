<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    protected $fillable = [
        'kategori_id',
        'nama',
        'gaya',
        'deskripsi',
        'foto',
        'harga',
        'status',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function opsi(): HasMany
    {
        return $this->hasMany(ProdukOpsi::class);
    }

    public function paket(): BelongsToMany
    {
        return $this->belongsToMany(Paket::class, 'paket_produk', 'produk_id', 'paket_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Bonus yang diberikan oleh produk ini.
     */
    public function bonus(): HasMany
    {
        return $this->hasMany(ProdukBonus::class, 'produk_id');
    }
}
