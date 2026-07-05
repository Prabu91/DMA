<?php

namespace App\Livewire\Sekolah;

use App\Models\Cabang;
use App\Models\Sekolah;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SekolahIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

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
            'nama', 'alamat', 'kota', 'pic_sekolah', 'no_telp_pic', 'email_guru', 'maps_link', 'cabang_id',
        ]));
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $sekolah = Sekolah::findOrFail($this->editingId);
            $this->authorize('update', $sekolah);
            // cabang_id & id_sekolah tidak diubah saat edit (menjaga konsistensi id).
            unset($data['cabang_id']);
            $sekolah->update($data);
            $this->success = 'Sekolah diperbarui.';
        } else {
            $this->authorize('create', Sekolah::class);

            $cabangId = $this->canChooseCabang() ? $this->cabang_id : auth()->user()->cabang_id;
            if (! $cabangId) {
                $this->error = 'Anda belum terhubung ke cabang. Hubungi admin.';

                return;
            }

            $cabang = Cabang::findOrFail($cabangId);
            $data['cabang_id'] = $cabangId;
            $data['id_sekolah'] = Sekolah::generateIdSekolah($cabang);

            $sekolah = Sekolah::create($data);
            $this->success = 'Sekolah ditambahkan dengan ID '.$sekolah->id_sekolah.'.';
        }

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

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'nama', 'alamat', 'kota', 'pic_sekolah',
            'no_telp_pic', 'email_guru', 'maps_link', 'cabang_id', 'idSekolahPreview',
        ]);
        $this->resetErrorBag();
    }

    public function render()
    {
        $sekolah = Sekolah::query()
            ->with('cabang')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', '%'.$this->search.'%')
                        ->orWhere('id_sekolah', 'ilike', '%'.$this->search.'%')
                        ->orWhere('kota', 'ilike', '%'.$this->search.'%');
                });
            })
            ->orderBy('nama')
            ->get();

        return view('livewire.sekolah.sekolah-index', compact('sekolah'));
    }
}
