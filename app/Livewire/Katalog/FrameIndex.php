<?php

namespace App\Livewire\Katalog;

use App\Livewire\Concerns\WithPerPage;
use App\Livewire\Concerns\WithSorting;
use App\Models\Frame;
use App\Models\Produk;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class FrameIndex extends Component
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

    public string $status = 'aktif';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Frame::class);
    }

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', array_keys(Frame::STATUS))],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', Frame::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $frame = Frame::findOrFail($id);
        $this->authorize('update', $frame);

        $this->success = null;
        $this->error = null;
        $this->editingId = $frame->id;
        $this->nama = $frame->nama;
        $this->status = $frame->status ?: 'aktif';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        // Nama frame unik.
        $duplikat = Frame::where('nama', $data['nama'])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();
        if ($duplikat) {
            $this->addError('nama', 'Frame dengan nama ini sudah ada.');

            return;
        }

        if ($this->editingId) {
            $frame = Frame::findOrFail($this->editingId);
            $this->authorize('update', $frame);
            $lama = $frame->nama;
            $frame->update($data);
            // Ikut perbarui produk yang memakai nama frame lama (karena disimpan sbg string).
            if ($lama !== $data['nama']) {
                Produk::where('frame', $lama)->update(['frame' => $data['nama']]);
            }
            $this->success = 'Frame diperbarui.';
        } else {
            $this->authorize('create', Frame::class);
            Frame::create($data);
            $this->success = 'Frame ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->reset(['success', 'error']);

        $frame = Frame::findOrFail($id);
        $this->authorize('delete', $frame);

        if (Produk::where('frame', $frame->nama)->exists()) {
            $this->error = 'Frame tidak bisa dihapus karena masih dipakai produk.';

            return;
        }

        $frame->delete();
        $this->success = 'Frame dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'status']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $frame = Frame::query()
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->withCount('produk');

        $frame = $this->applySort($frame, 'nama', 'asc')->paginate($this->perPage());

        return view('livewire.katalog.frame-index', compact('frame'));
    }
}
