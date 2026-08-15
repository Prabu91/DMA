<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'tipe_item',
        'produk_id',
        'paket_id',
        'desain_id',
        'opsi_ukuran',
        'qty',
        'harga',
        'diskon',
        'diskon_diajukan',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'harga' => 'integer',
            'qty' => 'integer',
            'diskon' => 'integer',
        ];
    }

    /** Harga satuan setelah diskon. */
    public function hargaEfektif(): int
    {
        return max(0, (int) $this->harga - (int) $this->diskon);
    }

    /** Subtotal baris setelah diskon (harga efektif × qty). */
    public function subtotalEfektif(): int
    {
        return $this->hargaEfektif() * (int) $this->qty;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class);
    }

    public function desain(): BelongsTo
    {
        return $this->belongsTo(Desain::class);
    }
}
