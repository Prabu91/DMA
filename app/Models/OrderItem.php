<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'tipe_item',
        'produk_id',
        'paket_id',
        'desain_id',
        'opsi_ukuran',
        'qty',
        'harga',
        'diskon',
        'diskon_diajukan',
        'is_free',
        'tanpa_redaksi',
        'qc_event_at',
        'qc_event_oleh',
        'qc_admin_at',
        'qc_admin_oleh',
    ];

    /**
     * Peran yang mencentang QC item. Marketing tidak ada di sini: item order
     * memang dibuat marketing, jadi konfirmasinya sudah melekat.
     */
    public const QC_PERAN = [
        'event' => 'Tim event',
        'admin' => 'Admin',
    ];

    protected static function booted(): void
    {
        // Item yang jumlah atau desainnya berubah bukan lagi item yang tadi
        // dinyatakan sesuai — centangnya dilepas, apa pun jalur perubahannya.
        static::updating(function (self $item) {
            if ($item->isDirty(['qty', 'desain_id'])) {
                foreach (array_keys(self::QC_PERAN) as $peran) {
                    $item->{"qc_{$peran}_at"} = null;
                    $item->{"qc_{$peran}_oleh"} = null;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'tanpa_redaksi' => 'boolean',
            'harga' => 'integer',
            'qty' => 'integer',
            'diskon' => 'integer',
            'qc_event_at' => 'datetime',
            'qc_admin_at' => 'datetime',
        ];
    }

    /** Harga satuan setelah diskon. */
    public function hargaEfektif(): int
    {
        return max(0, (int) $this->harga - (int) $this->diskon);
    }

    /** Subtotal baris setelah diskon (harga efektif × qty). */
    public function subtotalEfektif(): int
    {
        return $this->hargaEfektif() * (int) $this->qty;
    }

    /** Sudah dicentang peran ini? */
    public function sudahQc(string $peran): bool
    {
        return $this->{"qc_{$peran}_at"} !== null;
    }

    /** Centang / lepas centang QC untuk satu peran, mencatat siapa & kapan. */
    public function setQc(string $peran, bool $sesuai, ?int $userId): void
    {
        abort_unless(array_key_exists($peran, self::QC_PERAN), 422);

        $this->forceFill([
            "qc_{$peran}_at" => $sesuai ? now() : null,
            "qc_{$peran}_oleh" => $sesuai ? $userId : null,
        ])->saveQuietly();
    }

    public function qcEventOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_event_oleh');
    }

    public function qcAdminOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_admin_oleh');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function paket(): BelongsTo
    {
        return $this->belongsTo(Paket::class);
    }

    public function desain(): BelongsTo
    {
        return $this->belongsTo(Desain::class);
    }
}
