<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanFreeSekolah extends Model
{
    protected $table = 'aturan_free_sekolah';

    /** Basis evaluasi aturan. */
    public const BASIS = ['qty' => 'Jumlah siswa', 'omset' => 'Total omset'];

    /** Operator perbandingan ambang. */
    public const OPERATOR = ['>=' => '≥ (lebih dari atau sama dengan)', '<' => '< (kurang dari)'];

    protected $fillable = [
        'paket_id',
        'basis',
        'operator',
        'nilai',
        'hasil_produk_id',
        'hasil_ukuran',
    ];

    protected $casts = [
        'nilai' => 'integer',
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
