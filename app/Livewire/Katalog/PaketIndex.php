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

    public string $status = 'aktif';

    /** @var array<int, array{produk_id:?int, opsi_ukuran:?string, qty:int, harga:int, is_free:bool}> */
    public array $items = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Paket::class);
    }

    #[Computed]
    public function produkOptions(): array
    {
        return Produk::orderBy('nama')->pluck('nama', 'id')->all();
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
            'status' => ['required', 'in:'.implode(',', array_keys(Paket::STATUS))],
            'items' => ['array'],
            'items.*.produk_id' => ['required', 'exists:produk,id'],
            'items.*.opsi_ukuran' => ['nullable', 'string', 'max:100'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.harga' => ['required', 'integer', 'min:0'],
            'items.*.is_free' => ['boolean'],
        ];
    }

    protected array $messages = [
        'items.*.produk_id.required' => 'Pilih produk.',
    ];

    public function addItem(): void
    {
        $this->items[] = ['produk_id' => null, 'opsi_ukuran' => null, 'qty' => 1, 'harga' => 0, 'is_free' => false];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function create(): void
    {
        $this->authorize('create', Paket::class);
        $this->resetForm();
        $this->items = [['produk_id' => null, 'opsi_ukuran' => null, 'qty' => 1, 'harga' => 0, 'is_free' => false]];
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $paket = Paket::with('items')->findOrFail($id);
        $this->authorize('update', $paket);

        $this->success = null;
        $this->error = null;
        $this->editingId = $paket->id;
        $this->nama = $paket->nama;
        $this->deskripsi = $paket->deskripsi;
        $this->status = $paket->status ?: 'aktif';
        $this->items = $paket->items->map(fn ($it) => [
            'produk_id' => $it->produk_id,
            'opsi_ukuran' => $it->opsi_ukuran,
            'qty' => (int) $it->qty,
            'harga' => (int) $it->harga,
            'is_free' => (bool) $it->is_free,
        ])->values()->all();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        // Buang baris tanpa produk agar paket boleh kosong (draft).
        $this->items = array_values(array_filter($this->items, fn ($i) => ! empty($i['produk_id'])));

        $data = $this->validate();

        if ($this->editingId) {
            $paket = Paket::findOrFail($this->editingId);
            $this->authorize('update', $paket);
        } else {
            $this->authorize('create', Paket::class);
            $paket = new Paket;
        }

        // Harga paket = Σ item non-free (harga × qty) → konsisten dgn hargaJual.
        $hargaJual = collect($data['items'])
            ->reject(fn ($i) => (bool) ($i['is_free'] ?? false))
            ->sum(fn ($i) => (int) $i['harga'] * (int) $i['qty']);

        // Paket tanpa produk tidak bisa dijual → paksa nonaktif.
        $status = empty($data['items']) ? 'nonaktif' : $data['status'];

        $paket->fill([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'harga' => $hargaJual,
            'status' => $status,
        ])->save();

        // Sync paket_item: hapus-lalu-buat-ulang.
        $paket->items()->delete();
        foreach ($data['items'] as $it) {
            $paket->items()->create([
                'produk_id' => $it['produk_id'],
                'opsi_ukuran' => $it['opsi_ukuran'] ?: null,
                'qty' => $it['qty'],
                'harga' => $it['harga'],
                'is_free' => (bool) ($it['is_free'] ?? false),
            ]);
        }

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

        $paket->items()->delete();
        $paket->delete();
        $this->success = 'Paket dihapus.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'deskripsi', 'status', 'items']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function render()
    {
        $paket = Paket::query()
            ->withCount('items')
            ->when($this->search !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$this->search.'%'))
            ->orderBy('nama')
            ->get();

        return view('livewire.katalog.paket-index', compact('paket'));
    }
}
