<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    /** Gaya produk (atribut produk, tidak terkait desain). */
    public const GAYA = ['MINIMALIS', 'BLOK', '3D', 'GLITER', 'LEMBARAN'];

    /** Status produk. */
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    /** Satuan hitung produk (menentukan label input jumlah, bukan rumus harga). */
    public const SATUAN = ['qty' => 'Per item (qty)', 'siswa' => 'Per jumlah siswa'];

    protected $fillable = [
        'kategori_id',
        'nama',
        'gaya',
        'deskripsi',
        'foto',
        'harga',
        'satuan',
        'status',
    ];

    protected $casts = [
        'harga' => 'integer',
    ];

    protected $attributes = [
        'satuan' => 'qty',
    ];

    /** True bila jumlah pesan produk ini = jumlah siswa (harga × jumlah siswa). */
    public function isPerSiswa(): bool
    {
        return $this->satuan === 'siswa';
    }

    /** Label kolom input jumlah pada halaman pesan. */
    public function satuanLabel(): string
    {
        return $this->isPerSiswa() ? 'Jumlah siswa' : 'Jumlah';
    }

    /** Kata satuan singkat untuk sufiks harga/qty (mis. /siswa, /item). */
    public function satuanUnit(): string
    {
        return $this->isPerSiswa() ? 'siswa' : 'item';
    }

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
        return $this->belongsToMany(Paket::class, 'paket_produk', 'produk_id', 'paket_id');
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
