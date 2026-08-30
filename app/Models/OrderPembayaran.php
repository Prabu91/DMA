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
        self::JENIS_PELUNASAN => 'Pembayaran', // dulu "Pelunasan" — kini bisa dicicil >2x
    ];

    /** Status approval — hanya `approved` yang dihitung ke total dibayar. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS = [
        self::STATUS_PENDING => 'Menunggu approval',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    protected $fillable = [
        'order_id',
        'jenis',
        'jumlah',
        'status',
        'tanggal_bayar',
        'dicatat_oleh',
        'disetujui_oleh',
        'disetujui_at',
        'keterangan',
        'bukti_path',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'tanggal_bayar' => 'date',
            'disetujui_at' => 'datetime',
        ];
    }

    /** Hanya pembayaran yang sudah disetujui. */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
