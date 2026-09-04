<?php

namespace App\Livewire\Katalog;

use App\Livewire\Concerns\WithPerPage;
use App\Livewire\Concerns\WithSorting;
use App\Models\Kategori;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class KategoriIndex extends Component
{
    use WithPagination, WithPerPage, WithSorting;

    protected function sortableColumns(): array
    {
        return ['nama' => 'nama', 'produk' => 'produk_count'];
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    // Field form
    public string $nama = '';

    public bool $pakai_desain = false;

    public string $grup = 'reguler';

    // Notifikasi inline (Livewire tidak reload penuh, jadi tak pakai session flash)
    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Kategori::class);
    }

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'pakai_desain' => ['boolean'],
            'grup' => ['required', 'in:'.implode(',', array_keys(Kategori::GRUP))],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', Kategori::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $kategori = Kategori::findOrFail($id);
        $this->authorize('update', $kategori);

        $this->success = null;
        $this->error = null;
        $this->editingId = $kategori->id;
        $this->nama = $kategori->nama;
        $this->pakai_desain = (bool) $kategori->pakai_desain;
        $this->grup = $kategori->grup ?: 'reguler';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $kategori = Kategori::findOrFail($this->editingId);
            $this->authorize('update', $kategori);
            $kategori->update($data);
            $this->success = 'Kategori diperbarui.';
        } else {
            $this->authorize('create', Kategori::class);
            Kategori::create($data);
            $this->success = 'Kategori ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $kategori = Kategori::findOrFail($id);
        $this->authorize('delete', $kategori);

        if ($kategori->produk()->exists() || $kategori->desain()->exists()) {
            $this->error = 'Kategori tidak bisa dihapus karena masih dipakai produk atau desain.';

            return;
        }

        $kategori->delete();
        $this->success = 'Kategori dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'pakai_desain', 'grup']);
        $this->resetErrorBag();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $kategori = Kategori::query()
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->withCount(['produk', 'desain']);

        $kategori = $this->applySort($kategori, 'nama', 'asc')->paginate($this->perPage());

        return view('livewire.katalog.kategori-index', compact('kategori'));
    }
}
