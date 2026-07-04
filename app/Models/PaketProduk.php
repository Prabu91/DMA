<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PaketProduk extends Pivot
{
    protected $table = 'paket_produk';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['paket_id', 'produk_id'];
}
