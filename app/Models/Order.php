<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(CabangScope::class)]
class Order extends Model
{
    protected $table = 'orders';

    /** Jalur asal order. */
    public const SUMBER_SEKOLAH = 'sekolah';

    public const SUMBER_MARKETING = 'marketing';

    protected $fillable = [
        'booking_code',
        'sekolah_id',
        'marketing_id',
        'cabang_id',
        'sumber',
        'status',
        'event_status',
        'tanggal_event',
        'jam_event',
        'jumlah_siswa',
        'keterangan',
        'total',
        'otp_code',
        'otp_expires',
        'tahun_ajaran',
        'tanggal_booking',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_event' => 'date',
            'otp_expires' => 'datetime',
            'tanggal_booking' => 'datetime',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function timEvent(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_tim_event', 'order_id', 'user_id');
    }
}
