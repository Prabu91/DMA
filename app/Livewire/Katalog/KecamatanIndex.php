<?php

namespace App\Livewire\Katalog;

use App\Livewire\Concerns\WithSorting;
use App\Models\Kecamatan;
use App\Models\Kota;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Master kecamatan (di bawah kota) — dikelola super_admin.
 * Kecamatan = acuan pembagian wilayah marketing (auto-assign order sekolah).
 */
#[Layout('layouts.app')]
class KecamatanIndex extends Component
{
    use WithPagination, WithSorting;

    protected function sortableColumns(): array
    {
        return ['nama' => 'nama', 'sekolah' => 'sekolah_count', 'marketing' => 'users_count'];
    }

    /** Kembali ke halaman 1 saat filter/cari berubah. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKota(): void
    {
        $this->resetPage();
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public string $filterKota = '';

    // Field form
    public string $nama = '';

    public ?int $kota_id = null;

    public ?string $success = null;

    public ?string $error = null;

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'kota_id' => ['required', 'integer', 'exists:kota,id'],
        ];
    }

    #[Computed]
    public function kotaOptions(): array
    {
        return Kota::orderBy('nama')->pluck('nama', 'id')->all();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $kecamatan = Kecamatan::findOrFail($id);

        $this->success = null;
        $this->error = null;
        $this->editingId = $kecamatan->id;
        $this->nama = $kecamatan->nama;
        $this->kota_id = $kecamatan->kota_id;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        // Nama kecamatan unik dalam satu kota.
        $duplikat = Kecamatan::where('kota_id', $data['kota_id'])
            ->where('nama', $data['nama'])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();
        if ($duplikat) {
            $this->addError('nama', 'Kecamatan ini sudah ada di kota tersebut.');

            return;
        }

        if ($this->editingId) {
            Kecamatan::findOrFail($this->editingId)->update($data);
            $this->success = 'Kecamatan diperbarui.';
        } else {
            Kecamatan::create($data);
            $this->success = 'Kecamatan ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->reset(['success', 'error']);

        $kecamatan = Kecamatan::withCount(['sekolah', 'users'])->findOrFail($id);

        if ($kecamatan->sekolah_count > 0 || $kecamatan->users_count > 0) {
            $this->error = 'Kecamatan tidak bisa dihapus karena masih dipakai sekolah atau marketing.';

            return;
        }

        $kecamatan->delete();
        $this->success = 'Kecamatan dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'kota_id']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $kecamatan = Kecamatan::query()
            ->with('kota')
            ->when($this->filterKota !== '', fn ($q) => $q->where('kota_id', $this->filterKota))
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->withCount(['sekolah', 'users']);

        $kecamatan = $this->applySort($kecamatan, 'nama', 'asc')->paginate(20);

        return view('livewire.katalog.kecamatan-index', compact('kecamatan'));
    }
}
