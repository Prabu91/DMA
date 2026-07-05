<?php

namespace App\Livewire\Katalog;

use App\Models\AturanFreeSekolah;
use App\Models\Produk;
use App\Models\ProdukBonus;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProdukIndex extends Component
{
    public string $search = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Produk::class);
    }

    public function delete(int $id): void
    {
        $produk = Produk::findOrFail($id);
        $this->authorize('delete', $produk);

        // Cegah hapus bila masih direferensikan di tempat lain.
        $terpakai = $produk->paket()->exists()
            || $produk->orderItems()->exists()
            || ProdukBonus::where('bonus_produk_id', $produk->id)->exists()
            || AturanFreeSekolah::where('hasil_produk_id', $produk->id)->exists();

        if ($terpakai) {
            $this->error = 'Produk tidak bisa dihapus karena masih dipakai paket, order, bonus, atau aturan free.';

            return;
        }

        // Hapus opsi & bonus milik produk ini, lalu file & produk.
        $produk->opsi()->delete();
        $produk->bonus()->delete();
        if ($produk->foto) {
            Storage::disk('public')->delete($produk->foto);
        }
        $produk->delete();

        $this->success = 'Produk dihapus.';
    }

    public function render()
    {
        $produk = Produk::query()
            ->with('kategori')
            ->withCount(['opsi', 'bonus'])
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->orderBy('nama')
            ->get();

        return view('livewire.katalog.produk-index', compact('produk'));
    }
}
