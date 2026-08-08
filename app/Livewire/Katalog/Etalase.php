<?php

namespace App\Livewire\Katalog;

use App\Models\Kategori;
use App\Models\Paket;
use App\Support\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Etalase katalog (browsing) — dipakai ulang di konteks staf & sekolah.
 * Layout ditentukan oleh wrapper Blade yang meng-embed komponen ini.
 */
class Etalase extends Component
{
    public string $konteks = 'staf'; // 'staf' | 'sekolah' | 'publik'

    public string $search = '';

    public function mount(string $konteks = 'staf'): void
    {
        $this->konteks = in_array($konteks, ['sekolah', 'publik'], true) ? $konteks : 'staf';
    }

    #[Computed]
    public function paketList()
    {
        return Paket::query()
            ->where('status', 'aktif')
            ->withCount('produk')
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->orderBy('nama')
            ->get();
    }

    #[Computed]
    public function kategoriList()
    {
        return Kategori::query()
            ->with(['produk' => function ($q) {
                $q->where('status', 'aktif')
                    ->when($this->search !== '', fn ($sub) => $sub->where('nama', 'ilike', '%'.$this->search.'%'))
                    ->orderBy('nama');
            }])
            ->orderBy('nama')
            ->get()
            ->filter(fn ($k) => $k->produk->isNotEmpty());
    }

    public function detailUrl(string $tipe, int $id): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.katalog.detail', ['tipe' => $tipe, 'id' => $id]),
            'publik' => route('storefront.katalog.detail', ['tipe' => $tipe, 'id' => $id]),
            default => route('app.etalase.detail', ['tipe' => $tipe, 'id' => $id]),
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

    #[Computed]
    public function cartCount(): int
    {
        return app(Cart::class)->count();
    }

    public function render()
    {
        return view('livewire.katalog.etalase');
    }
}
