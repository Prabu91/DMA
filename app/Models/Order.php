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
        'konfirmasi_lokasi_oleh',
        'konfirmasi_h7_oleh',
        'konfirmasi_h2_oleh',
        'konfirmasi_hh_oleh',
        'event_selesai_at',
        'sampai_kantor_at',
        'tanggal_event',
        'jam_event',
        'jumlah_siswa',
        'keterangan',
        'bukti_dp_path',
        'total',
        'diskon_status',
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
            'sampai_kantor_at' => 'datetime',
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
            ['h7', 'H-7', $event->copy()->subDays(7), $this->konfirmasi_h7_at, $this->konfirmasiH7Oleh],
            ['h2', 'H-2', $event->copy()->subDays(2), $this->konfirmasi_h2_at, $this->konfirmasiH2Oleh],
            ['hh', 'Hari-H', $event->copy(), $this->konfirmasi_hh_at, $this->konfirmasiHhOleh],
        ];

        return array_map(function ($d) use ($today) {
            [$key, $label, $due, $confirmedAt, $oleh] = $d;
            // Berurutan: langkah terkunci bila prasyarat belum terpenuhi.
            if ($confirmedAt) {
                $state = 'confirmed';
            } elseif (! $this->milestoneTerbuka($key)) {
                $state = 'locked';
            } else {
                $state = $due->lt($today) ? 'overdue' : 'upcoming';
            }

            return ['key' => $key, 'label' => $label, 'due' => $due, 'confirmedAt' => $confirmedAt, 'oleh' => $oleh, 'state' => $state];
        }, $defs);
    }

    public const MILESTONE_COL = [
        'h7' => 'konfirmasi_h7_at',
        'h2' => 'konfirmasi_h2_at',
        'hh' => 'konfirmasi_hh_at',
    ];

    public const MILESTONE_OLEH_COL = [
        'h7' => 'konfirmasi_h7_oleh',
        'h2' => 'konfirmasi_h2_oleh',
        'hh' => 'konfirmasi_hh_oleh',
    ];

    /** Milestone H-7 & H-2 = wewenang admin sales; Hari-H = tim event. */
    public const MILESTONE_ADMIN = ['h7', 'h2'];

    /** Ada pembayaran DP yang sudah disetujui → langkah DP terpenuhi. */
    public function sudahDp(): bool
    {
        return in_array($this->status, [\App\Support\OrderStatus::DP, \App\Support\OrderStatus::LUNAS], true);
    }

    /**
     * Gating berurutan: DP → H-7 → H-2 → Hari-H.
     * Sebuah milestone hanya boleh dikonfirmasi bila prasyaratnya selesai.
     */
    public function milestoneTerbuka(string $key): bool
    {
        return match ($key) {
            'h7' => $this->sudahDp(),
            'h2' => $this->konfirmasi_h7_at !== null,
            'hh' => $this->konfirmasi_h2_at !== null,
            default => false,
        };
    }

    public function konfirmasiLokasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konfirmasi_lokasi_oleh');
    }

    public function konfirmasiH7Oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konfirmasi_h7_oleh');
    }

    public function konfirmasiH2Oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konfirmasi_h2_oleh');
    }

    public function konfirmasiHhOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konfirmasi_hh_oleh');
    }

    /**
     * Order TERKUNCI setelah tim event konfirmasi Hari-H (titik final) atau
     * event sudah selesai. Terkunci = tak boleh diubah siapa pun.
     */
    public function isLocked(): bool
    {
        return $this->konfirmasi_hh_at !== null
            || $this->event_status === \App\Support\OrderStatus::EVENT_SELESAI;
    }

    /**
     * Kunci berlaku untuk semua peran KECUALI super_admin — super_admin punya
     * bypass agar tetap bisa mengubah order bila ada keadaan khusus.
     */
    public function terkunciUntuk(?User $user): bool
    {
        if ($user && $user->hasRole('super_admin')) {
            return false;
        }

        return $this->isLocked();
    }

    /** Ada pembayaran berstatus pending (menunggu approval admin sales)? */
    public function adaPembayaranPending(): bool
    {
        if (array_key_exists('pembayaran_pending_count', $this->attributes)) {
            return (int) $this->attributes['pembayaran_pending_count'] > 0;
        }
        if ($this->relationLoaded('pembayaran')) {
            return $this->pembayaran->where('status', OrderPembayaran::STATUS_PENDING)->isNotEmpty();
        }

        return $this->pembayaran()->where('status', OrderPembayaran::STATUS_PENDING)->exists();
    }

    /**
     * Label status untuk tampilan: bila masih "baru" tapi ada DP menunggu
     * approval, tampilkan "Menunggu approval DP" (bukan "Menunggu DP").
     */
    public function statusLabel(): string
    {
        if ($this->status === \App\Support\OrderStatus::BARU && $this->adaPembayaranPending()) {
            return 'Menunggu approval DP';
        }

        return \App\Support\OrderStatus::label($this->status);
    }

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

    /**
     * Nomor WhatsApp tujuan notifikasi/OTP = no. telp PIC sekolah.
     */
    public function nomorWa(): ?string
    {
        return $this->sekolah?->no_telp_pic;
    }

    /**
     * Kirim pesan WhatsApp ke PIC sekolah via Fonnte (non-fatal).
     * Return true bila WA terkirim; false bila dilewati/gagal (mis. token/nomor kosong).
     */
    public function kirimWa(string $message): bool
    {
        return app(\App\Services\Notifications\FonnteService::class)
            ->send($this->nomorWa(), $message);
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

    public function pembayaran(): HasMany
    {
        return $this->hasMany(OrderPembayaran::class)->orderBy('tanggal_bayar');
    }

    // ---------- Finansial ----------

    /** Diskon disetujui (0 bila belum/ditolak). */
    public const DISKON_DIAJUKAN = 'diajukan';

    public const DISKON_DISETUJUI = 'disetujui';

    public const DISKON_DITOLAK = 'ditolak';

    /** Total diskon (Σ diskon per satuan × qty item non-free). */
    public function totalDiskon(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->where('is_free', false)
            ->sum(fn ($i) => (int) $i->diskon * (int) $i->qty);
    }

    /** Total setelah diskon per item. */
    public function totalSetelahDiskon(): int
    {
        return max(0, (int) $this->total - $this->totalDiskon());
    }

    /** Total sudah dibayar (Σ pembayaran DISETUJUI). Pakai relasi ter-load bila ada. */
    public function totalDibayar(): int
    {
        return (int) ($this->relationLoaded('pembayaran')
            ? $this->pembayaran->where('status', OrderPembayaran::STATUS_APPROVED)->sum('jumlah')
            : $this->pembayaran()->approved()->sum('jumlah'));
    }

    /** Sisa tagihan. */
    public function outstanding(): int
    {
        return max(0, $this->totalSetelahDiskon() - $this->totalDibayar());
    }

    /**
     * Grup kategori (bucket finance: reguler/ob/yb/souvenir) yang ada di order,
     * unik. Dihitung dari item→produk→kategori (paket: union produk isinya).
     * Butuh relasi items.produk.kategori & items.paket.produk.kategori ter-load.
     */
    public function grupKategori(): array
    {
        $grups = [];
        foreach ($this->items as $item) {
            if ($item->tipe_item === 'produk') {
                $grups[] = $item->produk?->kategori?->grup ?? 'reguler';
            } elseif ($item->tipe_item === 'paket' && $item->paket) {
                foreach ($item->paket->produk as $p) {
                    $grups[] = $p->kategori?->grup ?? 'reguler';
                }
            }
        }

        return array_values(array_unique($grups ?: ['reguler']));
    }

    /**
     * Sinkronkan kolom status dari total pembayaran vs tagihan.
     * Order batal tidak diubah. Dipanggil tiap catat/hapus pembayaran & approve diskon.
     */
    public function recalcStatusPembayaran(): void
    {
        if ($this->status === \App\Support\OrderStatus::BATAL) {
            return;
        }

        $dibayar = $this->totalDibayar();
        $tagihan = $this->totalSetelahDiskon();

        $status = match (true) {
            $tagihan > 0 && $dibayar >= $tagihan => \App\Support\OrderStatus::LUNAS,
            $dibayar > 0 => \App\Support\OrderStatus::DP,
            default => \App\Support\OrderStatus::BARU,
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Simpan foto bukti DP (1 foto): simpan yang baru, hapus yang lama, catat.
     * Dipakai bersama OrderDetail (staf) & EventDetail (tim event).
     */
    public function gantiBuktiDp(\Illuminate\Http\UploadedFile $file): void
    {
        $lama = $this->bukti_dp_path;
        $path = $file->store('bukti-bayar', 'public');
        $this->update(['bukti_dp_path' => $path]);

        if ($lama && $lama !== $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($lama);
        }

        $this->catat('bukti_dp', 'unggah bukti DP');
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
