<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paket extends Model
{
    protected $table = 'paket';

    /** Status paket. */
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    protected $fillable = ['nama', 'deskripsi', 'harga', 'status'];

    protected $casts = [
        'harga' => 'integer',
    ];

    /** Baris isi paket (kaya: produk+varian+qty+harga+free). Sumber utama. */
    public function items(): HasMany
    {
        return $this->hasMany(PaketItem::class);
    }

    /** Produk distinct dalam paket (derivasi dari paket_item; untuk tampilan/grup). */
    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'paket_item', 'paket_id', 'produk_id')->distinct();
    }

    /** Harga jual paket = Σ item non-free (harga × qty). */
    public function hargaJual(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->where('is_free', false)->sum(fn ($i) => (int) $i->harga * (int) $i->qty);
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
