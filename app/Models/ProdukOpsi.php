<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukOpsi extends Model
{
    protected $table = 'produk_opsi';

    protected $fillable = [
        'produk_id',
        'tipe_opsi',
        'nilai_opsi',
        'harga_override',
        'is_wajib',
    ];

    protected function casts(): array
    {
        return [
            'is_wajib' => 'boolean',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
