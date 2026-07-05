<?php

namespace App\Livewire\Katalog;

use App\Models\Paket;
use App\Models\Produk;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PaketIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public ?string $success = null;

    public ?string $error = null;

    // Field form
    public string $nama = '';

    public ?string $deskripsi = null;

    public int $harga = 0;

    public string $status = 'aktif';

    public array $selectedProduk = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Paket::class);
    }

    #[Computed]
    public function produkList()
    {
        return Produk::orderBy('nama')->get(['id', 'nama', 'harga']);
    }

    #[Computed]
    public function statusOptions(): array
    {
        return Paket::STATUS;
    }

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'harga' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:'.implode(',', array_keys(Paket::STATUS))],
            'selectedProduk' => ['array'],
            'selectedProduk.*' => ['exists:produk,id'],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', Paket::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $paket = Paket::findOrFail($id);
        $this->authorize('update', $paket);

        $this->success = null;
        $this->error = null;
        $this->editingId = $paket->id;
        $this->nama = $paket->nama;
        $this->deskripsi = $paket->deskripsi;
        $this->harga = (int) $paket->harga;
        $this->status = $paket->status ?: 'aktif';
        $this->selectedProduk = $paket->produk()->pluck('produk.id')->all();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $paket = Paket::findOrFail($this->editingId);
            $this->authorize('update', $paket);
        } else {
            $this->authorize('create', Paket::class);
            $paket = new Paket;
        }

        $paket->fill([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'harga' => $data['harga'],
            'status' => $data['status'],
        ])->save();

        $paket->produk()->sync($data['selectedProduk'] ?? []);

        $this->success = $this->editingId ? 'Paket diperbarui.' : 'Paket ditambahkan.';
        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $paket = Paket::findOrFail($id);
        $this->authorize('delete', $paket);

        if ($paket->orderItems()->exists() || $paket->aturanFreeSekolah()->exists()) {
            $this->error = 'Paket tidak bisa dihapus karena masih dipakai order atau aturan free sekolah.';

            return;
        }

        $paket->produk()->detach();
        $paket->delete();
        $this->success = 'Paket dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'deskripsi', 'harga', 'status', 'selectedProduk']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function render()
    {
        $paket = Paket::query()
            ->withCount('produk')
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->orderBy('nama')
            ->get();

        return view('livewire.katalog.paket-index', compact('paket'));
    }
}
