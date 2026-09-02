<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Desain extends Model
{
    protected $table = 'desain';

    /** Orientasi desain. */
    public const ORIENTASI = ['portrait' => 'Portrait', 'landscape' => 'Landscape'];

    /** Status desain. */
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    protected $fillable = [
        'kategori_id',
        'produk_id',
        'kode',
        'seri',
        'ukuran',
        'orientasi',
        'foto_preview',
        'tahun_ajaran',
        'status',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    /** Produk-produk yang memakai desain ini (many-to-many) + ukuran berlaku per produk. */
    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'desain_produk')
            ->using(DesainProduk::class)
            ->withPivot('ukuran')
            ->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
