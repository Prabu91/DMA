<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanFreeSekolah extends Model
{
    protected $table = 'aturan_free_sekolah';

    protected $fillable = [
        'paket_id',
        'basis',
        'operator',
        'nilai',
        'hasil_produk_id',
        'hasil_ukuran',
    ];

    public function paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class);
    }

    public function hasilProduk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'hasil_produk_id');
    }
}
