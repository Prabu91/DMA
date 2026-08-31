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

    public ?string $selectedUkuran = null;

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

        return Desain::where('kategori_id', $this->produk()->kategori_id)
            ->where('status', 'aktif')
            ->when($this->tahunAjaran, fn ($q) => $q->where('tahun_ajaran', $this->tahunAjaran))
            // Bila ukuran dipilih, tampilkan desain berlabel ukuran itu + desain tanpa label
            // (berlaku semua ukuran). Bila belum pilih ukuran, tampilkan semua.
            ->when($this->selectedUkuran, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('ukuran')->orWhere('ukuran', $this->selectedUkuran)))
            ->orderBy('kode')
            ->get();
    }

    /** Saat ukuran berubah, lepas pilihan desain bila tak lagi cocok dengan ukuran. */
    public function updatedSelectedUkuran(): void
    {
        unset($this->desainPool); // segarkan computed
        if ($this->selectedDesain && ! $this->desainPool->contains('id', $this->selectedDesain)) {
            $this->selectedDesain = null;
        }
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
            $adaOpsiWajib = $produk->opsi->firstWhere('is_wajib', true) !== null;
            if ($adaOpsiWajib && ! $this->selectedUkuran) {
                $this->addError('selectedUkuran', 'Silakan pilih ukuran (wajib).');
            }
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $cart->add([
                'tipe_item' => 'produk',
                'produk_id' => $this->id,
                'desain_id' => $this->selectedDesain,
                'opsi_ukuran' => $this->selectedUkuran,
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
