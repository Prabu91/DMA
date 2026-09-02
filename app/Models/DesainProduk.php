<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DesainProduk extends Pivot
{
    protected $table = 'desain_produk';

    // opsi ukuran yang berlaku; [] / null = semua ukuran
    protected $casts = [
        'ukuran' => 'array',
    ];
}
