<?php

namespace App\Livewire\Katalog;

use App\Livewire\Concerns\WithPerPage;
use App\Models\AturanFreeSekolah;
use App\Models\Paket;
use App\Models\Produk;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AturanFreeIndex extends Component
{
    use WithPagination, WithPerPage;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $filterPaket = null;

    public ?string $success = null;

    public ?string $error = null;

    // Field form
    public ?int $paket_id = null;

    public string $basis = 'qty';

    public string $operator = '>=';

    public int $nilai = 0;

    public ?int $hasil_produk_id = null;

    public ?string $hasil_ukuran = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AturanFreeSekolah::class);
    }

    #[Computed]
    public function paketOptions(): array
    {
        return Paket::orderBy('nama')->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function produkOptions(): array
    {
        return Produk::orderBy('nama')->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function basisOptions(): array
    {
        return AturanFreeSekolah::BASIS;
    }

    #[Computed]
    public function operatorOptions(): array
    {
        return AturanFreeSekolah::OPERATOR;
    }

    protected function rules(): array
    {
        return [
            'paket_id' => ['required', 'exists:paket,id'],
            'basis' => ['required', 'in:'.implode(',', array_keys(AturanFreeSekolah::BASIS))],
            'operator' => ['required', 'in:'.implode(',', array_keys(AturanFreeSekolah::OPERATOR))],
            'nilai' => ['required', 'integer', 'min:0'],
            'hasil_produk_id' => ['required', 'exists:produk,id'],
            'hasil_ukuran' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', AturanFreeSekolah::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $aturan = AturanFreeSekolah::findOrFail($id);
        $this->authorize('update', $aturan);

        $this->success = null;
        $this->error = null;
        $this->editingId = $aturan->id;
        $this->paket_id = $aturan->paket_id;
        $this->basis = $aturan->basis;
        $this->operator = $aturan->operator;
        $this->nilai = (int) $aturan->nilai;
        $this->hasil_produk_id = $aturan->hasil_produk_id;
        $this->hasil_ukuran = $aturan->hasil_ukuran;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $aturan = AturanFreeSekolah::findOrFail($this->editingId);
            $this->authorize('update', $aturan);
            $aturan->update($data);
            $this->success = 'Aturan diperbarui.';
        } else {
            $this->authorize('create', AturanFreeSekolah::class);
            AturanFreeSekolah::create($data);
            $this->success = 'Aturan ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $aturan = AturanFreeSekolah::findOrFail($id);
        $this->authorize('delete', $aturan);
        $aturan->delete();
        $this->success = 'Aturan dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'paket_id', 'basis', 'operator', 'nilai', 'hasil_produk_id', 'hasil_ukuran']);
        $this->basis = 'qty';
        $this->operator = '>=';
        $this->resetErrorBag();
    }

    public function updatedFilterPaket(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $aturan = AturanFreeSekolah::query()
            ->with(['paket', 'hasilProduk'])
            ->when($this->filterPaket, fn ($q) => $q->where('paket_id', $this->filterPaket))
            ->orderBy('paket_id')
            ->orderBy('nilai')
            ->paginate($this->perPage());

        return view('livewire.katalog.aturan-free-index', compact('aturan'));
    }
}
