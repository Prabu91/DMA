<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Cabang;
use App\Models\Kecamatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Daftar & kelola pengguna staf.
 *
 * Sebelumnya berupa halaman biasa: menekan "Ubah" memuat ulang halaman dan
 * filter cabang/role yang sedang dipasang ikut hilang, sehingga harus dicari
 * ulang tiap kali. Kini form dibuka sebagai modal di halaman yang sama, jadi
 * filternya tetap terpasang.
 */
#[Layout('layouts.app')]
class PenggunaIndex extends Component
{
    use WithPagination, WithPerPage;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterCabang = '';

    #[Url]
    public string $filterRole = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    // Field form
    public string $nama = '';

    public string $email = '';

    public ?string $no_telp = null;

    public ?string $role = null;

    /** Cabang yang dipegang — sederajat, boleh lebih dari satu. */
    public array $cabangIds = [];

    public array $kecamatanIds = [];

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCabang(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function cabangOptions(): array
    {
        return Cabang::orderBy('nama')->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function roleOptions(): array
    {
        return Role::orderBy('name')->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => Str::headline($name)])
            ->all();
    }

    /** Role lintas cabang tidak perlu ditugaskan ke cabang mana pun. */
    #[Computed]
    public function roleLintasCabang(): bool
    {
        return in_array($this->role, User::ROLES_LINTAS_CABANG, true);
    }

    /**
     * Kecamatan yang bisa dipilih: berada di dalam cabang yang dipegang user
     * ini, dan belum dipegang marketing lain (satu kecamatan satu PIC).
     */
    #[Computed]
    public function kecamatanOptions(): array
    {
        if ($this->role !== 'marketing' || $this->cabangIds === []) {
            return [];
        }

        $dipegangOrangLain = DB::table('user_kecamatan')
            ->when($this->editingId, fn ($q) => $q->where('user_id', '!=', $this->editingId))
            ->pluck('kecamatan_id')
            ->all();

        return Kecamatan::query()
            ->whereHas('kota', fn ($q) => $q->whereIn('cabang_id', $this->cabangIds))
            ->when($dipegangOrangLain, fn ($q) => $q->whereNotIn('id', $dipegangOrangLain))
            ->with('kota')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn ($k) => [$k->id => $k->nama.' — '.($k->kota?->nama ?? '—')])
            ->all();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $pengguna = User::with(['cabangs', 'kecamatan', 'roles'])->findOrFail($id);

        $this->resetForm();
        $this->editingId = $pengguna->id;
        $this->nama = $pengguna->nama ?: $pengguna->name;
        $this->email = $pengguna->email;
        $this->no_telp = $pengguna->no_telp;
        $this->role = $pengguna->roles->first()?->name;
        $this->cabangIds = $pengguna->cabangIds();
        $this->kecamatanIds = $pengguna->kecamatan->pluck('id')->all();
        $this->showForm = true;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'nama', 'email', 'no_telp', 'role', 'cabangIds', 'kecamatanIds', 'password', 'password_confirmation']);
        $this->resetErrorBag();
        $this->success = null;
        $this->error = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'no_telp' => ['nullable', 'string', 'max:30'],
            'role' => ['nullable', Rule::in(Role::pluck('name')->all())],
            'cabangIds' => ['array'],
            'cabangIds.*' => ['integer', Rule::exists('cabang', 'id')],
            'kecamatanIds' => ['array'],
            'kecamatanIds.*' => ['integer', Rule::exists('kecamatan', 'id')],
            'password' => $this->editingId
                ? ['nullable', 'confirmed', Password::defaults()]
                : ['required', 'confirmed', Password::defaults()],
        ]);

        // Role lintas cabang melihat semua cabang, jadi penugasan cabang diabaikan.
        $cabangIds = $this->roleLintasCabang ? [] : array_values(array_unique($data['cabangIds'] ?? []));

        $pengguna = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $pengguna->fill([
            'nama' => $data['nama'],
            'name' => $data['nama'],   // sinkron dengan kolom bawaan Breeze
            'email' => $data['email'],
            'no_telp' => $data['no_telp'] ?? null,
            'role' => $data['role'] ?? null,
            // Kolom lama tetap diisi salah satu cabang agar data lama tetap terbaca;
            // sumber kebenarannya tabel cabang_user.
            'cabang_id' => $cabangIds[0] ?? null,
        ]);

        if (! empty($data['password'])) {
            $pengguna->password = Hash::make($data['password']);
        }

        $pengguna->save();
        $pengguna->cabangs()->sync($cabangIds);
        $pengguna->lupakanCabangIds();
        $pengguna->syncRoles($data['role'] ? [$data['role']] : []);
        $this->syncKecamatan($pengguna, $cabangIds);

        $pesan = $this->editingId ? 'Pengguna diperbarui.' : 'Pengguna ditambahkan.';

        $this->showForm = false;
        $this->resetForm();          // ikut mengosongkan success/error...
        $this->success = $pesan;     // ...jadi pesannya dipasang sesudahnya.
    }

    /** Kecamatan hanya untuk marketing, dan harus di dalam cabang yang ia pegang. */
    private function syncKecamatan(User $pengguna, array $cabangIds): void
    {
        if (($pengguna->roles->first()?->name ?? $this->role) !== 'marketing' || $cabangIds === []) {
            $pengguna->kecamatan()->sync([]);

            return;
        }

        $dipegangOrangLain = DB::table('user_kecamatan')
            ->where('user_id', '!=', $pengguna->id)
            ->pluck('kecamatan_id')
            ->all();

        $valid = Kecamatan::whereIn('id', $this->kecamatanIds)
            ->when($dipegangOrangLain, fn ($q) => $q->whereNotIn('id', $dipegangOrangLain))
            ->whereHas('kota', fn ($q) => $q->whereIn('cabang_id', $cabangIds))
            ->pluck('id')
            ->all();

        $pengguna->kecamatan()->sync($valid);
    }

    public function delete(int $id): void
    {
        $this->reset(['success', 'error']);

        $pengguna = User::findOrFail($id);

        if ($pengguna->is(auth()->user())) {
            $this->error = 'Anda tidak dapat menghapus akun sendiri.';

            return;
        }

        // orders.marketing_id & order_tim_event.user_id memakai RESTRICT di database.
        // Tanpa penjagaan ini, menghapus user yang masih dirujuk melempar galat FK
        // mentah — itulah error 500 yang dilaporkan. Query mentah dipakai supaya
        // order milik cabang lain & yang sudah dihapus tetap ikut terhitung.
        $jumlahOrder = DB::table('orders')->where('marketing_id', $pengguna->id)->count();
        $jumlahEvent = DB::table('order_tim_event')->where('user_id', $pengguna->id)->count();

        if ($jumlahOrder > 0 || $jumlahEvent > 0) {
            $sebab = [];
            if ($jumlahOrder > 0) {
                $sebab[] = "penanggung jawab {$jumlahOrder} order";
            }
            if ($jumlahEvent > 0) {
                $sebab[] = "anggota tim di {$jumlahEvent} event";
            }

            $this->error = 'Pengguna tidak bisa dihapus karena masih tercatat sebagai '
                .implode(' dan ', $sebab).'. Riwayat order harus tetap punya penanggung jawab.';

            return;
        }

        $pengguna->delete();
        $this->success = 'Pengguna dihapus.';
    }

    public function render()
    {
        $pengguna = User::query()
            ->with(['cabangs', 'cabang', 'roles'])
            ->when($this->search !== '', function ($q) {
                $t = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('nama', 'ilike', $t)
                    ->orWhere('name', 'ilike', $t)
                    ->orWhere('email', 'ilike', $t));
            })
            ->when($this->filterCabang !== '', function ($q) {
                // Cocokkan pivot maupun kolom lama, karena keduanya masih terbaca.
                $q->where(fn ($w) => $w->whereHas('cabangs', fn ($c) => $c->where('cabang.id', $this->filterCabang))
                    ->orWhere('cabang_id', $this->filterCabang));
            })
            ->when($this->filterRole !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->filterRole)))
            ->orderBy('nama')
            ->orderBy('name')
            ->paginate($this->perPage());

        return view('livewire.pengguna-index', compact('pengguna'));
    }
}
