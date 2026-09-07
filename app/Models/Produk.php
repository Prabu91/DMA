<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    /** Status produk. */
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    protected $fillable = [
        'kategori_id',
        'nama',
        'frame',
        'komposisi_ukuran',
        'maks_pcs',
        'deskripsi',
        'foto',
        'harga',
        'status',
    ];

    protected $casts = [
        'harga' => 'integer',
        'komposisi_ukuran' => 'boolean',
        'maks_pcs' => 'integer',
    ];

    /** Batas pcs bila mode komposisi menyala (0 = tak dibatasi). */
    public const MAKS_PCS_DEFAULT = 20;

    /**
     * Mode komposisi ukuran aktif? Butuh saklar menyala DAN ada nilai varian
     * bertipe "ukuran" untuk dibagi jatahnya.
     */
    public function pakaiKomposisiUkuran(): bool
    {
        return $this->komposisi_ukuran && count($this->ukuranOpsi()) > 0;
    }

    public function maksPcs(): int
    {
        return $this->maks_pcs > 0 ? (int) $this->maks_pcs : self::MAKS_PCS_DEFAULT;
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function opsi(): HasMany
    {
        return $this->hasMany(ProdukOpsi::class);
    }

    /** Desain yang dipakai produk ini (many-to-many) + ukuran berlaku (pivot). */
    public function desains(): BelongsToMany
    {
        return $this->belongsToMany(Desain::class, 'desain_produk')
            ->using(DesainProduk::class)
            ->withPivot('ukuran')
            ->withTimestamps();
    }

    /**
     * Harga satuan setelah memperhitungkan nilai varian yang dipilih.
     *
     * $dipilih = daftar nilai_opsi terpilih (mis. ['60 HALAMAN', 'BOX']).
     * Aturannya:
     *   - varian penentu harga (is_tambahan = false) MENGGANTI harga produk;
     *   - varian tambahan (is_tambahan = true) DITAMBAHKAN di atasnya.
     * Nilai tanpa harga_override tidak mengubah apa pun.
     *
     * Satu-satunya sumber kebenaran harga: dipakai keranjang, order, dan
     * tampilan katalog supaya angkanya tidak pernah berbeda.
     *
     * @param  array<int, string>  $dipilih
     */
    public function hargaSatuan(array $dipilih = []): int
    {
        $unit = (int) $this->harga;
        $tambahan = 0;

        foreach (array_filter($dipilih, fn ($n) => $n !== null && $n !== '') as $nilai) {
            $opsi = $this->opsi->firstWhere('nilai_opsi', $nilai);
            if (! $opsi || $opsi->harga_override === null) {
                continue;
            }

            if ($opsi->is_tambahan) {
                $tambahan += (int) $opsi->harga_override;
            } else {
                $unit = (int) $opsi->harga_override;
            }
        }

        return max(0, $unit + $tambahan);
    }

    /** Nilai opsi ukuran produk ini (untuk checkbox ukuran pada desain). */
    public function ukuranOpsi(): array
    {
        return $this->opsi
            ? $this->opsi->where('tipe_opsi', 'ukuran')->pluck('nilai_opsi')->filter()->values()->all()
            : $this->opsi()->where('tipe_opsi', 'ukuran')->pluck('nilai_opsi')->filter()->values()->all();
    }

    public function paket(): BelongsToMany
    {
        return $this->belongsToMany(Paket::class, 'paket_item', 'produk_id', 'paket_id')->distinct();
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
