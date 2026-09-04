<?php

namespace App\Livewire\Katalog;

use App\Models\Desain;
use App\Models\Paket;
use App\Models\Produk;
use App\Support\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Detail item katalog (produk satuan atau paket). Untuk item berdesain,
 * menampilkan pool DESAIN kategori (filter tahun_ajaran aktif) + opsi ukuran.
 * Dipakai ulang di konteks staf & sekolah (layout dari wrapper).
 *
 * Pemilihan bersifat lokal (state UI); keranjang & simpan = Fase 3.
 */
class EtalaseDetail extends Component
{
    public string $konteks = 'staf';

    public string $tipe;   // 'produk' | 'paket'

    public int $id;

    // Pilihan lokal
    public ?int $selectedDesain = null;

    /**
     * Pilihan varian per tipe: ['box' => 'TANPA BOX', 'ukuran' => '10RP'].
     * Produk bisa punya beberapa tipe varian, jadi tiap tipe dipilih terpisah.
     */
    public array $pilihan = [];

    public ?string $tahunAjaran = null;

    public int $qty = 1;

    public bool $justAdded = false;

    public function mount(string $konteks, string $tipe, int $id): void
    {
        abort_unless(in_array($tipe, ['produk', 'paket'], true), 404);

        $this->konteks = in_array($konteks, ['sekolah', 'publik'], true) ? $konteks : 'staf';
        $this->tipe = $tipe;
        $this->id = $id;

        // Default tahun ajaran "aktif" = tahun terbaru pada desain aktif kategori ini.
        if ($this->pakaiDesain()) {
            $this->tahunAjaran = Desain::where('kategori_id', $this->produk()->kategori_id)
                ->where('status', 'aktif')
                ->whereNotNull('tahun_ajaran')
                ->max('tahun_ajaran');
        }
    }

    #[Computed]
    public function produk(): ?Produk
    {
        return $this->tipe === 'produk'
            ? Produk::with(['kategori', 'opsi'])->findOrFail($this->id)
            : null;
    }

    #[Computed]
    public function paket(): ?Paket
    {
        return $this->tipe === 'paket'
            ? Paket::with('produk')->findOrFail($this->id)
            : null;
    }

    #[Computed]
    public function pakaiDesain(): bool
    {
        return $this->tipe === 'produk' && (bool) $this->produk()->kategori?->pakai_desain;
    }

    /** Varian produk dikelompokkan per tipe — satu tipe = satu kartu pilihan. */
    #[Computed]
    public function variantGroups()
    {
        return $this->tipe === 'produk'
            ? $this->produk()->opsi->groupBy('tipe_opsi')
            : collect();
    }

    /**
     * Nilai varian bertipe "ukuran" — hanya tipe inilah yang menyaring desain,
     * karena pivot desain_produk menyimpan daftar ukuran yang berlaku.
     */
    #[Computed]
    public function selectedUkuran(): ?string
    {
        foreach ($this->pilihan as $tipe => $nilai) {
            if (mb_strtolower(trim((string) $tipe)) === 'ukuran' && $nilai !== '' && $nilai !== null) {
                return $nilai;
            }
        }

        return null;
    }

    #[Computed]
    public function tahunOptions(): array
    {
        if (! $this->pakaiDesain()) {
            return [];
        }

        return Desain::where('kategori_id', $this->produk()->kategori_id)
            ->where('status', 'aktif')
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->orderByDesc('tahun_ajaran')
            ->pluck('tahun_ajaran', 'tahun_ajaran')
            ->all();
    }

    #[Computed]
    public function desainPool()
    {
        if (! $this->pakaiDesain()) {
            return collect();
        }

        $pid = $this->produk()->id;

        $pool = Desain::where('status', 'aktif')
            ->when($this->tahunAjaran, fn ($q) => $q->where('tahun_ajaran', $this->tahunAjaran))
            // Desain yang ditempel ke produk ini (pivot desain_produk).
            ->whereHas('products', fn ($q) => $q->where('produk.id', $pid))
            ->with(['products' => fn ($q) => $q->where('produk.id', $pid)])
            ->orderBy('kode')
            ->get();

        // Filter ukuran: pivot.ukuran kosong = semua ukuran; selain itu harus memuat ukuran terpilih.
        $ukuran = $this->selectedUkuran;
        if ($ukuran) {
            $pool = $pool->filter(function ($d) use ($ukuran) {
                $uk = $d->products->first()?->pivot->ukuran ?? [];
                if (is_string($uk)) {
                    $uk = json_decode($uk, true) ?: [];
                }

                return empty($uk) || in_array($ukuran, $uk, true);
            })->values();
        }

        return $pool;
    }

    /** Saat pilihan varian berubah, lepas desain bila tak lagi cocok dengan ukuran. */
    public function updatedPilihan(): void
    {
        unset($this->selectedUkuran, $this->desainPool); // segarkan computed
        if ($this->selectedDesain && ! $this->desainPool->contains('id', $this->selectedDesain)) {
            $this->selectedDesain = null;
        }
    }

    /** "box" -> "Box", "ukuran" -> "Ukuran" — dipakai judul kartu & pesan galat. */
    public static function labelVarian(string $tipe): string
    {
        return ucwords(mb_strtolower(trim($tipe)));
    }

    public function indexUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.katalog.index'),
            'publik' => route('storefront.katalog.index'),
            default => route('app.etalase.index'),
        };
    }

    public function keranjangUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.keranjang'),
            'publik' => route('storefront.keranjang'),
            default => route('app.keranjang'),
        };
    }

    public function tambah(Cart $cart): void
    {
        $this->justAdded = false;
        $this->resetErrorBag();
        $qty = max(1, $this->qty);

        if ($this->tipe === 'paket') {
            $cart->add(['tipe_item' => 'paket', 'paket_id' => $this->id, 'qty' => $qty]);
        } else {
            $produk = $this->produk();

            // Validasi: desain wajib bila pool ada; ukuran wajib bila ada opsi is_wajib.
            if ($this->pakaiDesain() && $this->desainPool->isNotEmpty() && ! $this->selectedDesain) {
                $this->addError('selectedDesain', 'Silakan pilih desain terlebih dahulu.');
            }
            // Tiap tipe varian yang wajib harus dipilih sendiri-sendiri.
            foreach ($this->variantGroups as $tipe => $nilaiOpsi) {
                $wajib = $nilaiOpsi->contains(fn ($o) => $o->is_wajib);
                if ($wajib && ($this->pilihan[$tipe] ?? null) === null) {
                    $this->addError('pilihan.'.$tipe, 'Silakan pilih '.self::labelVarian($tipe).' (wajib).');
                }
            }
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            // Hanya tipe yang benar-benar dipilih; urutannya ikut urutan varian produk.
            $opsi = [];
            foreach ($this->variantGroups as $tipe => $nilaiOpsi) {
                $nilai = $this->pilihan[$tipe] ?? null;
                if ($nilai !== null && $nilai !== '') {
                    $opsi[$tipe] = $nilai;
                }
            }

            $cart->add([
                'tipe_item' => 'produk',
                'produk_id' => $this->id,
                'desain_id' => $this->selectedDesain,
                'opsi' => $opsi,
                // Snapshot ringkas untuk order_items / PDF / daftar order.
                'opsi_ukuran' => $opsi ? implode(' · ', $opsi) : null,
                'qty' => $qty,
            ]);
        }

        $this->justAdded = true;
        // Sertakan jumlah terbaru agar badge keranjang bisa update realtime.
        $this->dispatch('cart-updated', count: $cart->count());
    }

    public function render()
    {
        return view('livewire.katalog.etalase-detail');
    }
}
