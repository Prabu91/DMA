<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaketItem extends Model
{
    protected $table = 'paket_item';

    protected $fillable = [
        'paket_id',
        'produk_id',
        'opsi_ukuran',
        'desain_id',
        'qty',
        'harga',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'harga' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
