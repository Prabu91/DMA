<?php

namespace App\Livewire\Katalog;

use App\Models\Desain;
use App\Models\Kategori;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class DesainIndex extends Component
{
    use WithFileUploads;

    // Filter daftar
    public ?int $filterKategori = null;

    public ?string $filterTahun = null;

    public string $search = '';

    // Modal & form
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $kategori_id = null;

    public string $kode = '';

    public ?string $seri = null;

    public ?string $ukuran = null;

    public ?string $orientasi = null;

    public string $tahun_ajaran = '';

    public string $status = 'aktif';

    public $foto_preview = null;

    public ?string $fotoExisting = null;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Desain::class);
    }

    /** Kategori untuk form: hanya yang memakai desain. */
    #[Computed]
    public function kategoriDesainOptions(): array
    {
        return Kategori::where('pakai_desain', true)->orderBy('nama')->pluck('nama', 'id')->all();
    }

    /** Kategori untuk filter (semua kategori memakai desain). */
    #[Computed]
    public function kategoriFilterOptions(): array
    {
        return $this->kategoriDesainOptions();
    }

    #[Computed]
    public function tahunOptions(): array
    {
        return Desain::query()
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->orderByDesc('tahun_ajaran')
            ->pluck('tahun_ajaran', 'tahun_ajaran')
            ->all();
    }

    #[Computed]
    public function orientasiOptions(): array
    {
        return Desain::ORIENTASI;
    }

    #[Computed]
    public function statusOptions(): array
    {
        return Desain::STATUS;
    }

    protected function rules(): array
    {
        return [
            'kategori_id' => ['required', Rule::exists('kategori', 'id')->where('pakai_desain', true)],
            'kode' => ['required', 'string', 'max:100', Rule::unique('desain', 'kode')->ignore($this->editingId)],
            'seri' => ['nullable', 'string', 'max:100'],
            'ukuran' => ['nullable', 'string', 'max:20'],
            'orientasi' => ['nullable', 'in:'.implode(',', array_keys(Desain::ORIENTASI))],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:'.implode(',', array_keys(Desain::STATUS))],
            'foto_preview' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', Desain::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $desain = Desain::findOrFail($id);
        $this->authorize('update', $desain);

        $this->success = null;
        $this->error = null;
        $this->editingId = $desain->id;
        $this->kategori_id = $desain->kategori_id;
        $this->kode = $desain->kode;
        $this->seri = $desain->seri;
        $this->ukuran = $desain->ukuran;
        $this->orientasi = $desain->orientasi;
        $this->tahun_ajaran = $desain->tahun_ajaran ?? '';
        $this->status = $desain->status ?: 'aktif';
        $this->fotoExisting = $desain->foto_preview;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $desain = $this->editingId ? Desain::findOrFail($this->editingId) : new Desain;
        $this->authorize($this->editingId ? 'update' : 'create', $this->editingId ? $desain : Desain::class);

        $data = [
            'kategori_id' => $this->kategori_id,
            'kode' => $this->kode,
            'seri' => $this->seri,
            'ukuran' => $this->ukuran ?: null,
            'orientasi' => $this->orientasi,
            'tahun_ajaran' => $this->tahun_ajaran,
            'status' => $this->status,
        ];

        if ($this->foto_preview) {
            if ($desain->foto_preview) {
                Storage::disk('public')->delete($desain->foto_preview);
            }
            $data['foto_preview'] = $this->foto_preview->store('desain', 'public');
        }

        $desain->fill($data)->save();

        $this->success = $this->editingId ? 'Desain diperbarui.' : 'Desain ditambahkan.';
        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $desain = Desain::findOrFail($id);
        $this->authorize('delete', $desain);

        if ($desain->orderItems()->exists()) {
            $this->error = 'Desain tidak bisa dihapus karena sudah dipakai di order.';

            return;
        }

        if ($desain->foto_preview) {
            Storage::disk('public')->delete($desain->foto_preview);
        }
        $desain->delete();
        $this->success = 'Desain dihapus.';
    }

    /**
     * Hapus paksa (super admin) — walau desain masih dipakai order berjalan.
     * Referensi di order lama dilepas (desain_id → null), bukan ikut terhapus.
     */
    public function forceDelete(int $id): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        $desain = Desain::findOrFail($id);
        $this->authorize('delete', $desain);

        $dipakai = \App\Models\OrderItem::where('desain_id', $desain->id)->update(['desain_id' => null]);

        if ($desain->foto_preview) {
            Storage::disk('public')->delete($desain->foto_preview);
        }
        $desain->delete();
        $this->success = "Desain dihapus paksa. {$dipakai} item order melepas referensi desain ini.";
    }

    #[\Livewire\Attributes\Computed]
    public function isSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /** Nilai opsi ukuran yang dipakai produk (saran isian field Ukuran). */
    #[\Livewire\Attributes\Computed]
    public function ukuranTersedia(): array
    {
        return \App\Models\ProdukOpsi::where('tipe_opsi', 'ukuran')
            ->whereNotNull('nilai_opsi')
            ->where('nilai_opsi', '!=', '')
            ->distinct()
            ->orderBy('nilai_opsi')
            ->pluck('nilai_opsi')
            ->all();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'kategori_id', 'kode', 'seri', 'ukuran', 'orientasi', 'tahun_ajaran', 'status', 'foto_preview', 'fotoExisting']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function render()
    {
        $desain = Desain::query()
            ->with('kategori')
            ->withCount('orderItems')
            ->when($this->filterKategori, fn ($q) => $q->where('kategori_id', $this->filterKategori))
            ->when($this->filterTahun, fn ($q) => $q->where('tahun_ajaran', $this->filterTahun))
            ->when($this->search !== '', fn ($q) => $q->where('kode', 'ilike', '%'.$this->search.'%'))
            ->orderBy('kode')
            ->get();

        return view('livewire.katalog.desain-index', compact('desain'));
    }
}
