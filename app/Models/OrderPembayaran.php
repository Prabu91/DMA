<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPembayaran extends Model
{
    protected $table = 'order_pembayaran';

    /** Jenis pembayaran. */
    public const JENIS_DP = 'dp';

    public const JENIS_PELUNASAN = 'pelunasan';

    public const JENIS = [
        self::JENIS_DP => 'DP',
        self::JENIS_PELUNASAN => 'Pelunasan',
    ];

    protected $fillable = [
        'order_id',
        'jenis',
        'jumlah',
        'tanggal_bayar',
        'dicatat_oleh',
        'keterangan',
        'bukti_path',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'tanggal_bayar' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
