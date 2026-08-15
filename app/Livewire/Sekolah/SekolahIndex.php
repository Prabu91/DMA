<?php

namespace App\Livewire\Sekolah;

use App\Livewire\Concerns\WithSorting;
use App\Models\Cabang;
use App\Models\Kecamatan;
use App\Models\Sekolah;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SekolahIndex extends Component
{
    use WithSorting;

    protected function sortableColumns(): array
    {
        return ['nama' => 'nama', 'kota' => 'kota', 'kategori' => 'deal_count'];
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public string $filterCabang = ''; // filter cabang (admin lintas cabang)

    public string $filterKategori = ''; // NOS | NRS | SR

    // Reset password login sekolah (aksi staf terpisah)
    public bool $showPasswordModal = false;

    public ?int $passwordSekolahId = null;

    public ?string $passwordSekolahNama = null;

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public ?string $success = null;

    public ?string $error = null;

    // Field form
    public string $nama = '';

    public ?string $alamat = null;

    public ?string $kota = null;

    public ?string $pic_sekolah = null;

    public ?string $no_telp_pic = null;

    public ?string $email_guru = null;

    public ?string $maps_link = null;

    public ?int $cabang_id = null;

    public ?int $kecamatan_id = null;

    public ?string $idSekolahPreview = null; // read-only saat edit

    public function mount(): void
    {
        $this->authorize('viewAny', Sekolah::class);
    }

    /** super_admin & operasional boleh memilih cabang; selain itu terkunci ke cabang user. */
    #[Computed]
    public function canChooseCabang(): bool
    {
        return auth()->user()->seesAllCabang();
    }

    #[Computed]
    public function cabangOptions(): array
    {
        return Cabang::orderBy('nama')->pluck('nama', 'id')->all();
    }

    /** Cabang efektif form (admin: pilihan; selain itu: cabang user). */
    private function effectiveCabangId(): ?int
    {
        return $this->canChooseCabang() ? $this->cabang_id : auth()->user()->cabang_id;
    }

    /** Kecamatan dalam cabang form ([id => "Kecamatan — Kota"]). Reaktif thd cabang_id. */
    #[Computed]
    public function kecamatanOptions(): array
    {
        $cabangId = $this->effectiveCabangId();
        if (! $cabangId) {
            return [];
        }

        return Kecamatan::with('kota')
            ->whereHas('kota', fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn ($k) => [$k->id => $k->nama.' — '.$k->kota?->nama])
            ->all();
    }

    protected function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'kota' => ['nullable', 'string', 'max:255'],
            'pic_sekolah' => ['nullable', 'string', 'max:255'],
            'no_telp_pic' => ['nullable', 'string', 'max:30'],
            'email_guru' => ['nullable', 'email', 'max:255'],
            'maps_link' => ['nullable', 'url', 'max:1000'],
            'kecamatan_id' => ['nullable', 'integer', 'exists:kecamatan,id'],
            'cabang_id' => $this->canChooseCabang()
                ? ['required', 'exists:cabang,id']
                : ['nullable'],
        ];
    }

    public function create(): void
    {
        $this->authorize('create', Sekolah::class);
        $this->resetForm();
        $this->success = null;
        $this->error = null;

        if (! $this->canChooseCabang()) {
            $this->cabang_id = auth()->user()->cabang_id;
        }

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $sekolah = Sekolah::findOrFail($id); // ter-scope: hanya cabang user (kecuali lintas cabang)
        $this->authorize('update', $sekolah);

        $this->success = null;
        $this->error = null;
        $this->editingId = $sekolah->id;
        $this->idSekolahPreview = $sekolah->id_sekolah;
        $this->fill($sekolah->only([
            'nama', 'alamat', 'kota', 'pic_sekolah', 'no_telp_pic', 'email_guru', 'maps_link', 'cabang_id', 'kecamatan_id',
        ]));
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        // Cegah duplikat: kombinasi nama + PIC + no. telp + alamat harus unik.
        if (Sekolah::comboExists([
            'nama' => $this->nama,
            'pic_sekolah' => $this->pic_sekolah,
            'no_telp_pic' => $this->no_telp_pic,
            'alamat' => $this->alamat,
        ], $this->editingId)) {
            $this->addError('nama', 'Data sekolah (nama, PIC, no. telp, alamat) sudah terdaftar.');

            return;
        }

        if ($this->editingId) {
            $sekolah = Sekolah::findOrFail($this->editingId);
            $this->authorize('update', $sekolah);
            // cabang_id & id_sekolah tidak diubah saat edit (menjaga konsistensi id).
            unset($data['cabang_id']);
            // Kecamatan hanya sah bila milik cabang sekolah.
            $data['kecamatan_id'] = $this->kecamatanSah($data['kecamatan_id'] ?? null, $sekolah->cabang_id);
            $sekolah->update($data);
            $this->success = 'Sekolah diperbarui.';
        } else {
            $this->authorize('create', Sekolah::class);

            $cabangId = $this->canChooseCabang() ? $this->cabang_id : auth()->user()->cabang_id;
            if (! $cabangId) {
                $this->error = 'Anda belum terhubung ke cabang. Hubungi admin.';

                return;
            }

            $data['cabang_id'] = $cabangId;
            $data['kecamatan_id'] = $this->kecamatanSah($data['kecamatan_id'] ?? null, $cabangId);
            $data['id_sekolah'] = Sekolah::generateIdSekolah();

            $sekolah = Sekolah::create($data);
            $this->success = 'Sekolah ditambahkan dengan ID '.$sekolah->id_sekolah.'.';
        }

        unset($this->kecamatanOptions);
        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $sekolah = Sekolah::findOrFail($id);
        $this->authorize('delete', $sekolah);

        if ($sekolah->orders()->exists()) {
            $this->error = 'Sekolah tidak bisa dihapus karena masih memiliki order.';

            return;
        }

        $sekolah->delete();
        $this->success = 'Sekolah dihapus.';
    }

    public function openResetPassword(int $id): void
    {
        $sekolah = Sekolah::findOrFail($id);
        $this->authorize('update', $sekolah);

        $this->passwordSekolahId = $sekolah->id;
        $this->passwordSekolahNama = $sekolah->nama;
        $this->newPassword = '';
        $this->newPassword_confirmation = '';
        $this->success = null;
        $this->error = null;
        $this->resetErrorBag();
        $this->showPasswordModal = true;
    }

    public function savePassword(): void
    {
        $sekolah = Sekolah::findOrFail($this->passwordSekolahId);
        $this->authorize('update', $sekolah);

        $this->validate(
            ['newPassword' => ['required', 'confirmed', Password::defaults()]],
            [],
            ['newPassword' => 'kata sandi'],
        );

        // cast 'hashed' pada model Sekolah meng-hash otomatis.
        $sekolah->password = $this->newPassword;
        $sekolah->save();

        $this->showPasswordModal = false;
        $this->success = 'Kata sandi login '.$sekolah->nama.' berhasil diatur.';
    }

    /** Kembalikan kecamatan_id bila sah untuk $cabangId, selain itu null. */
    private function kecamatanSah(?int $kecamatanId, ?int $cabangId): ?int
    {
        if (! $kecamatanId || ! $cabangId) {
            return null;
        }

        $sah = Kecamatan::whereKey($kecamatanId)
            ->whereHas('kota', fn ($q) => $q->where('cabang_id', $cabangId))
            ->exists();

        return $sah ? $kecamatanId : null;
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'nama', 'alamat', 'kota', 'pic_sekolah',
            'no_telp_pic', 'email_guru', 'maps_link', 'cabang_id', 'kecamatan_id', 'idSekolahPreview',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        $selesai = fn ($q) => $q->where('event_status', \App\Support\OrderStatus::EVENT_SELESAI);

        $sekolah = Sekolah::query()
            ->with(['cabang', 'kecamatan'])
            ->withCount(['orders as deal_count' => $selesai])
            ->when($this->filterCabang !== '' && $this->canChooseCabang(), fn ($q) => $q->where('cabang_id', $this->filterCabang))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', '%'.$this->search.'%')
                        ->orWhere('id_sekolah', 'ilike', '%'.$this->search.'%')
                        ->orWhere('kota', 'ilike', '%'.$this->search.'%');
                });
            })
            // Filter kategori pelanggan lewat jumlah order selesai.
            ->when($this->filterKategori === Sekolah::KATEGORI_NOS, fn ($q) => $q->whereDoesntHave('orders', $selesai))
            ->when($this->filterKategori === Sekolah::KATEGORI_NRS, fn ($q) => $q->whereHas('orders', $selesai, '>=', 1)->whereHas('orders', $selesai, '<=', 2))
            ->when($this->filterKategori === Sekolah::KATEGORI_SR, fn ($q) => $q->whereHas('orders', $selesai, '>=', Sekolah::KATEGORI_AMBANG_SR));

        $sekolah = $this->applySort($sekolah, 'nama', 'asc')->get();

        return view('livewire.sekolah.sekolah-index', compact('sekolah'));
    }
}
