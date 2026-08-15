<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Support\OrderStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ScopedBy(CabangScope::class)]
class Sekolah extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'sekolah';

    protected $fillable = [
        'id_sekolah',
        'nama',
        'alamat',
        'kota',
        'pic_sekolah',
        'no_telp_pic',
        'email_guru',
        'maps_link',
        'cabang_id',
        'kecamatan_id',
        // Storefront (login mandiri sekolah)
        'email',
        'google_id', // future-proof Socialite — belum dibangun
        'avatar',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Kategori pelanggan berdasarkan jumlah order yang event-nya SELESAI (deal tuntas).
     * NOS: belum pernah (0) · NRS: 1–2 · SR: ≥3.
     */
    public const KATEGORI_NOS = 'NOS';

    public const KATEGORI_NRS = 'NRS';

    public const KATEGORI_SR = 'SR';

    /** Ambang bawah tiap kategori (jumlah deal selesai). */
    public const KATEGORI_AMBANG_SR = 3;

    public const KATEGORI_AMBANG_NRS = 1;

    /**
     * Jumlah "deal" = order dengan event_status selesai. Memakai kolom
     * deal_count bila sudah di-load via withCount, selain itu query langsung.
     */
    public function dealCount(): int
    {
        if (array_key_exists('deal_count', $this->attributes)) {
            return (int) $this->attributes['deal_count'];
        }

        return $this->orders()->where('event_status', OrderStatus::EVENT_SELESAI)->count();
    }

    /** Kode kategori pelanggan (NOS/NRS/SR) dari jumlah deal. */
    public function kategoriPelanggan(): string
    {
        $n = $this->dealCount();

        return match (true) {
            $n >= self::KATEGORI_AMBANG_SR => self::KATEGORI_SR,
            $n >= self::KATEGORI_AMBANG_NRS => self::KATEGORI_NRS,
            default => self::KATEGORI_NOS,
        };
    }

    public static function kategoriLabel(string $kode): string
    {
        return match ($kode) {
            self::KATEGORI_NOS => 'NOS · belum pernah order',
            self::KATEGORI_NRS => 'NRS · 1–2 order',
            self::KATEGORI_SR => 'SR · pelanggan setia',
            default => $kode,
        };
    }

    /** Varian x-badge untuk kategori pelanggan. */
    public static function kategoriBadge(string $kode): string
    {
        return match ($kode) {
            self::KATEGORI_SR => 'success',
            self::KATEGORI_NRS => 'info',
            default => 'neutral',
        };
    }

    /**
     * Kolom yang membentuk identitas unik sebuah sekolah (kombinasi).
     * Dua entri dengan keempat nilai ini sama persis dianggap duplikat.
     */
    public const UNIQUE_COMBO = ['nama', 'pic_sekolah', 'no_telp_pic', 'alamat'];

    /**
     * Apakah sudah ada sekolah lain dengan kombinasi (nama, PIC, no. telp, alamat)
     * yang sama? Lintas cabang (withoutGlobalScopes). $ignoreId untuk update.
     * Nilai kosong ('') diperlakukan sebagai NULL agar cocok dengan cara simpan.
     */
    public static function comboExists(array $data, ?int $ignoreId = null): bool
    {
        $q = static::withoutGlobalScopes();

        if ($ignoreId) {
            $q->whereKeyNot($ignoreId);
        }

        foreach (self::UNIQUE_COMBO as $col) {
            $val = $data[$col] ?? null;
            $val = is_string($val) ? trim($val) : $val;

            // Nilai kosong cocok baik NULL maupun '' (kolom opsional bisa tersimpan salah satunya).
            ($val === null || $val === '')
                ? $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, ''))
                : $q->where($col, $val);
        }

        return $q->exists();
    }

    /**
     * Auto-generate id_sekolah unik (kode akun) — GLOBAL: SKL-000001.
     * Sengaja tidak per-cabang karena registrasi mandiri bisa tanpa cabang
     * (kota "lainnya"). Format placeholder, mudah diubah di satu tempat ini.
     * withoutGlobalScopes agar sekuens akurat lintas konteks.
     */
    public static function generateIdSekolah(): string
    {
        $prefix = 'SKL-';

        $max = static::withoutGlobalScopes()
            ->where('id_sekolah', 'like', $prefix.'%')
            ->pluck('id_sekolah')
            ->map(fn ($id) => (int) substr($id, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
