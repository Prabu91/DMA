<?php

namespace App\Livewire\Katalog;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\OrderItem;
use App\Services\DesainBulkUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DesainIndex extends Component
{
    use WithFileUploads, WithPagination, WithPerPage;

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

    public ?string $orientasi = null;

    public string $tahun_ajaran = '';

    public string $status = 'aktif';

    public $foto_preview = null;

    public ?string $fotoExisting = null;

    // Unggah massal: banyak berkas sekaligus, kode diambil dari nama berkas.
    public bool $showBulk = false;

    public array $bulkFiles = [];

    public ?int $bulkKategoriId = null;

    public string $bulkTahun = '';

    // Pilih banyak untuk dihapus sekaligus.
    public array $terpilih = [];

    public bool $konfirmasiHapusMassal = false;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Desain::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTahun(): void
    {
        $this->resetPage();
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
            'orientasi' => ['nullable', 'in:'.implode(',', array_keys(Desain::ORIENTASI))],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:'.implode(',', array_keys(Desain::STATUS))],
            'foto_preview' => ['nullable', 'image', 'max:4096'],
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
        $this->reset(['success', 'error']);

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
        $this->reset(['success', 'error']);

        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        $desain = Desain::findOrFail($id);
        $this->authorize('delete', $desain);

        $dipakai = OrderItem::where('desain_id', $desain->id)->update(['desain_id' => null]);

        if ($desain->foto_preview) {
            Storage::disk('public')->delete($desain->foto_preview);
        }
        $desain->delete();
        $this->success = "Desain dihapus paksa. {$dipakai} item order melepas referensi desain ini.";
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    // ---------------- Unggah massal ----------------

    public function bukaBulk(): void
    {
        $this->authorize('create', Desain::class);
        $this->reset(['success', 'error', 'bulkFiles']);
        $this->resetErrorBag();
        $this->bulkKategoriId = $this->filterKategori ?: array_key_first($this->kategoriDesainOptions);
        $this->bulkTahun = $this->filterTahun ?: $this->tahunAjaranDefault();
        $this->showBulk = true;
    }

    public function tutupBulk(): void
    {
        $this->showBulk = false;
        $this->reset('bulkFiles');
        $this->resetErrorBag();
    }

    /** Tahun ajaran berjalan (Juli = pergantian tahun ajaran). */
    private function tahunAjaranDefault(): string
    {
        $y = (int) now()->year;

        return now()->month >= 7 ? $y.'/'.($y + 1) : ($y - 1).'/'.$y;
    }

    public function simpanBulk(): void
    {
        $this->authorize('create', Desain::class);
        $this->reset(['success', 'error']);

        $this->validate([
            'bulkKategoriId' => ['required', Rule::exists('kategori', 'id')->where('pakai_desain', true)],
            'bulkTahun' => ['required', 'string', 'max:20'],
            'bulkFiles' => ['required', 'array', 'min:1', 'max:'.DesainBulkUpload::MAKS_BERKAS],
            'bulkFiles.*' => ['image', 'max:4096'],
        ], [
            'bulkFiles.required' => 'Pilih dulu berkas desainnya.',
            'bulkFiles.max' => 'Maksimal '.DesainBulkUpload::MAKS_BERKAS.' berkas sekali unggah.',
            'bulkFiles.*.image' => 'Semua berkas harus berupa gambar.',
            'bulkFiles.*.max' => 'Tiap berkas maksimal 4 MB.',
        ]);

        $hasil = app(DesainBulkUpload::class)->jalankan(
            $this->bulkFiles,
            (int) $this->bulkKategoriId,
            $this->bulkTahun,
        );

        $this->showBulk = false;
        $this->reset('bulkFiles');
        unset($this->tahunOptions);
        $this->resetPage();

        $this->success = $this->ringkasBulk($hasil);
    }

    /** @param  array{dibuat: array, dilewati: array, gagal: array}  $hasil */
    private function ringkasBulk(array $hasil): string
    {
        $pesan = count($hasil['dibuat']).' desain ditambahkan.';

        if ($hasil['dilewati']) {
            $pesan .= ' '.count($hasil['dilewati']).' dilewati karena kodenya sudah ada ('
                .implode(', ', array_slice($hasil['dilewati'], 0, 5))
                .(count($hasil['dilewati']) > 5 ? ', …' : '').').';
        }
        if ($hasil['gagal']) {
            $pesan .= ' '.count($hasil['gagal']).' gagal: '.implode('; ', $hasil['gagal']).'.';
        }

        return $pesan;
    }

    // ---------------- Hapus massal ----------------

    /** Desain terpilih yang benar-benar boleh dihapus + yang tertahan, beserta alasannya. */
    #[Computed]
    public function rencanaHapusMassal(): array
    {
        $ids = array_values(array_filter(array_map('intval', $this->terpilih)));
        if (! $ids) {
            return ['boleh' => collect(), 'tertahan' => collect()];
        }

        $rows = Desain::whereIn('id', $ids)->withCount(['orderItems', 'products'])->orderBy('kode')->get();

        return [
            'boleh' => $rows->filter(fn ($d) => $d->order_items_count === 0 && $d->products_count === 0),
            'tertahan' => $rows->filter(fn ($d) => $d->order_items_count > 0 || $d->products_count > 0),
        ];
    }

    public function mintaHapusMassal(): void
    {
        $this->reset(['success', 'error']);

        if (! $this->terpilih) {
            $this->error = 'Belum ada desain yang dipilih.';

            return;
        }

        unset($this->rencanaHapusMassal);
        $this->konfirmasiHapusMassal = true;
    }

    public function batalHapusMassal(): void
    {
        $this->konfirmasiHapusMassal = false;
    }

    /**
     * Hapus desain terpilih. Yang masih dipakai order atau masih menempel di
     * produk SENGAJA dilewati, bukan menggagalkan seluruh batch: pengguna
     * memilih sepuluh dan satu terpakai, yang sembilan tetap harus bersih.
     */
    public function hapusMassal(): void
    {
        $this->reset(['success', 'error']);
        $this->konfirmasiHapusMassal = false;

        $rencana = $this->rencanaHapusMassal;
        $dihapus = 0;

        foreach ($rencana['boleh'] as $desain) {
            $this->authorize('delete', $desain);

            if ($desain->foto_preview) {
                Storage::disk('public')->delete($desain->foto_preview);
            }
            $desain->delete();
            $dihapus++;
        }

        $tertahan = $rencana['tertahan']->count();

        $this->terpilih = [];
        unset($this->rencanaHapusMassal, $this->tahunOptions);
        $this->resetPage();

        $this->success = $dihapus.' desain dihapus.'
            .($tertahan > 0 ? ' '.$tertahan.' dilewati karena masih dipakai order atau masih menempel di produk.' : '');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'kategori_id', 'kode', 'seri', 'orientasi', 'tahun_ajaran', 'status', 'foto_preview', 'fotoExisting']);
        $this->status = 'aktif';
        $this->resetErrorBag();
    }

    public function render()
    {
        $desain = Desain::query()
            ->with(['kategori', 'products:id,nama'])
            ->withCount(['orderItems', 'products'])
            ->when($this->filterKategori, fn ($q) => $q->where('kategori_id', $this->filterKategori))
            ->when($this->filterTahun, fn ($q) => $q->where('tahun_ajaran', $this->filterTahun))
            ->when($this->search !== '', fn ($q) => $q->where('kode', 'ilike', '%'.$this->search.'%'))
            ->orderBy('kode')
            ->paginate($this->perPage());

        return view('livewire.katalog.desain-index', compact('desain'));
    }
}
