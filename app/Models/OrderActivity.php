<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu entri log aktivitas pada sebuah order (siapa, apa, kapan).
 * created_at diisi manual; tak ada updated_at.
 */
class OrderActivity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_activities';

    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /** Label & ikon per action (untuk timeline). */
    public const LABELS = [
        'dibuat' => 'Order dibuat',
        'marketing_diambil' => 'Diambil marketing',
        'marketing_ditugaskan' => 'Ditugaskan ke marketing',
        'status_dp' => 'DP dikonfirmasi',
        'status_lunas' => 'Ditandai lunas',
        'status_batal' => 'Order dibatalkan',
        'status_baru' => 'Diaktifkan kembali',
        'jadwal' => 'Jadwal event diperbarui',
        'milestone_h7' => 'Konfirmasi H-7',
        'milestone_h2' => 'Konfirmasi H-2',
        'milestone_hh' => 'Konfirmasi Hari-H',
        'tim_event' => 'Tim event ditugaskan',
        'konfirmasi_lokasi' => 'Detail dikonfirmasi di lokasi',
        'revisi' => 'Revisi detail order',
        'otp_dibuat' => 'OTP penyelesaian dibuat',
        'event_selesai' => 'Event selesai',
    ];

    public function label(): string
    {
        return self::LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /** Warna badge kasar berdasar jenis aksi. */
    public function tone(): string
    {
        return match (true) {
            in_array($this->action, ['status_batal'], true) => 'danger',
            in_array($this->action, ['status_lunas', 'event_selesai', 'konfirmasi_lokasi'], true) => 'success',
            str_starts_with($this->action, 'milestone_') => 'info',
            default => 'neutral',
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
