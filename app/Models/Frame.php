<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frame = atribut produk (dulu "gaya"). Master ber-CRUD; produk menyimpan
 * nama frame pada kolom `produk.frame`.
 */
class Frame extends Model
{
    protected $table = 'frame';

    protected $fillable = ['nama', 'status'];

    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    /** Produk yang memakai frame ini (relasi lewat nama, bukan FK). */
    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'frame', 'nama');
    }
}
