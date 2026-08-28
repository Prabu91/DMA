<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    /** Status produk. */
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    protected $fillable = [
        'kategori_id',
        'nama',
        'frame',
        'deskripsi',
        'foto',
        'harga',
        'status',
    ];

    protected $casts = [
        'harga' => 'integer',
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
        return $this->belongsToMany(Paket::class, 'paket_item', 'produk_id', 'paket_id')->distinct();
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
