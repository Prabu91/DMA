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
        'konfirmasi_h7_at',
        'konfirmasi_h2_at',
        'konfirmasi_hh_at',
        'konfirmasi_lokasi_at',
        'event_selesai_at',
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
            'konfirmasi_h7_at' => 'datetime',
            'konfirmasi_h2_at' => 'datetime',
            'konfirmasi_hh_at' => 'datetime',
            'konfirmasi_lokasi_at' => 'datetime',
            'event_selesai_at' => 'datetime',
            'otp_expires' => 'datetime',
            'tanggal_booking' => 'datetime',
        ];
    }

    /**
     * Hitung mundur menuju tanggal event. Null bila tanggal event belum diisi.
     *
     * @return array{days:int, label:string, state:string}|null
     */
    public function eventCountdown(): ?array
    {
        if (! $this->tanggal_event) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->tanggal_event->copy()->startOfDay(), false);
        $state = $days > 0 ? 'upcoming' : ($days === 0 ? 'today' : 'past');
        $label = $days > 0 ? 'H-'.$days : ($days === 0 ? 'Hari ini' : 'H+'.abs($days));

        return ['days' => $days, 'label' => $label, 'state' => $state];
    }

    /**
     * Milestone event (H-7 / H-2 / Hari-H) beserta status:
     * confirmed | overdue (jatuh tempo lewat, belum konfirmasi) | upcoming.
     *
     * @return array<int, array{key:string, label:string, due:\Carbon\CarbonInterface, confirmedAt:?\Carbon\CarbonInterface, state:string}>
     */
    public function milestones(): array
    {
        if (! $this->tanggal_event) {
            return [];
        }

        $event = $this->tanggal_event->copy()->startOfDay();
        $today = now()->startOfDay();

        $defs = [
            ['h7', 'H-7', $event->copy()->subDays(7), $this->konfirmasi_h7_at],
            ['h2', 'H-2', $event->copy()->subDays(2), $this->konfirmasi_h2_at],
            ['hh', 'Hari-H', $event->copy(), $this->konfirmasi_hh_at],
        ];

        return array_map(function ($d) use ($today) {
            [$key, $label, $due, $confirmedAt] = $d;
            $state = $confirmedAt ? 'confirmed' : ($due->lt($today) ? 'overdue' : 'upcoming');

            return ['key' => $key, 'label' => $label, 'due' => $due, 'confirmedAt' => $confirmedAt, 'state' => $state];
        }, $defs);
    }

    public const MILESTONE_COL = [
        'h7' => 'konfirmasi_h7_at',
        'h2' => 'konfirmasi_h2_at',
        'hh' => 'konfirmasi_hh_at',
    ];

    /** Masa berlaku OTP penyelesaian event (menit). */
    public const OTP_EXPIRY_MINUTES = 30;

    /** Jeda minimum antar kirim-ulang OTP (detik). */
    public const OTP_RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Buat OTP penyelesaian event (6 digit) + masa berlaku. Kode dikirim ke
     * guru (portal + email); tim event mengetik ulang kode dari guru.
     */
    public function generateEventOtp(?int $minutes = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'otp_code' => $code,
            'otp_expires' => now()->addMinutes($minutes ?? self::OTP_EXPIRY_MINUTES),
        ]);

        return $code;
    }

    /** OTP masih aktif (ada & belum kedaluwarsa). */
    public function eventOtpActive(): bool
    {
        return $this->otp_code !== null
            && $this->otp_expires !== null
            && $this->otp_expires->isFuture();
    }

    /**
     * Sisa detik cooldown sebelum boleh kirim-ulang OTP (0 = boleh sekarang).
     * Waktu kirim terakhir diturunkan dari otp_expires - masa berlaku.
     */
    public function otpResendSecondsLeft(): int
    {
        if (! $this->otp_expires) {
            return 0;
        }

        $bolehLagi = $this->otp_expires->copy()
            ->subMinutes(self::OTP_EXPIRY_MINUTES)
            ->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS);

        return max(0, $bolehLagi->getTimestamp() - now()->getTimestamp());
    }

    /** Cocokkan input OTP dengan yang aktif (aman terhadap timing). */
    public function eventOtpMatches(string $code): bool
    {
        return $this->eventOtpActive()
            && hash_equals((string) $this->otp_code, trim($code));
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

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->latest('created_at');
    }

    /**
     * Catat satu aktivitas order (siapa, apa, kapan). Pelaku default = user
     * staf yang login (null bila jalur sekolah / sistem).
     */
    public function catat(string $action, ?string $description = null, array $meta = [], ?int $userId = null): OrderActivity
    {
        // Pelaku hanya user staf (guard web); jalur sekolah/sistem → null.
        return $this->activities()->create([
            'user_id' => $userId ?? auth('web')->id(),
            'action' => $action,
            'description' => $description,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
