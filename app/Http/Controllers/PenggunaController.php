<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Kecamatan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PenggunaController extends Controller
{
    public function index(Request $request): View
    {
        $filterCabang = (string) $request->query('cabang', '');
        $filterRole = (string) $request->query('role', '');

        $users = User::with(['cabang', 'roles'])
            ->when($filterCabang !== '', fn ($q) => $q->where('cabang_id', $filterCabang))
            ->when($filterRole !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $filterRole)))
            ->orderBy('nama')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('pengguna.index', [
            'users' => $users,
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'roleOptions' => Role::orderBy('name')->pluck('name')
                ->mapWithKeys(fn ($name) => [$name => \Illuminate\Support\Str::headline($name)])
                ->all(),
            'filterCabang' => $filterCabang,
            'filterRole' => $filterRole,
        ]);
    }

    public function create(): View
    {
        return view('pengguna.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $user = new User;
        $this->fillUser($user, $data);
        $user->password = Hash::make($data['password']);
        $user->save();

        $this->syncRole($user, $data['role'] ?? null);
        $this->syncKecamatan($user, $data);

        return redirect()->route('app.pengguna.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(User $pengguna): View
    {
        return view('pengguna.edit', $this->formData(['pengguna' => $pengguna]));
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $data = $this->validated($request, $pengguna);

        $this->fillUser($pengguna, $data);
        if (! empty($data['password'])) {
            $pengguna->password = Hash::make($data['password']);
        }
        $pengguna->save();

        $this->syncRole($pengguna, $data['role'] ?? null);
        $this->syncKecamatan($pengguna, $data);

        return redirect()->route('app.pengguna.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $pengguna->delete();

        return redirect()->route('app.pengguna.index')->with('success', 'Pengguna dihapus.');
    }

    /**
     * Data pilihan untuk form (cabang, role, kecamatan per cabang, kecamatan terpilih).
     */
    private function formData(array $extra = []): array
    {
        $pengguna = $extra['pengguna'] ?? null;

        return array_merge([
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'roleOptions' => Role::orderBy('name')->pluck('name')
                ->mapWithKeys(fn ($name) => [$name => \Illuminate\Support\Str::headline($name)])
                ->all(),
            // Kecamatan dikelompokkan per cabang (untuk checkbox dependent di form).
            'kecamatanByCabang' => Kecamatan::with('kota')->orderBy('nama')->get()
                ->groupBy(fn ($k) => $k->kota?->cabang_id)
                ->reject(fn ($g, $cabangId) => $cabangId === null || $cabangId === '')
                ->map(fn ($g) => $g->map(fn ($k) => ['id' => $k->id, 'label' => $k->nama.' — '.$k->kota?->nama])->values())
                ->all(),
            'selectedKecamatan' => old('kecamatan_ids', $pengguna?->kecamatan->pluck('id')->all() ?? []),
        ], $extra);
    }

    private function validated(Request $request, ?User $pengguna): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($pengguna?->id)],
            'no_telp' => ['nullable', 'string', 'max:30'],
            'cabang_id' => ['nullable', Rule::exists('cabang', 'id')],
            'role' => ['nullable', Rule::in(Role::pluck('name')->all())],
            'kecamatan_ids' => ['nullable', 'array'],
            'kecamatan_ids.*' => ['integer', Rule::exists('kecamatan', 'id')],
            'password' => $pengguna
                ? ['nullable', 'confirmed', Password::defaults()]
                : ['required', 'confirmed', Password::defaults()],
        ]);
    }

    private function fillUser(User $user, array $data): void
    {
        $role = $data['role'] ?? null;
        // super_admin & operasional bersifat lintas cabang → cabang_id null.
        $cabangId = in_array($role, User::ROLES_LINTAS_CABANG, true) ? null : ($data['cabang_id'] ?? null);

        $user->fill([
            'nama' => $data['nama'],
            'name' => $data['nama'],        // sinkron dengan kolom Breeze
            'email' => $data['email'],
            'no_telp' => $data['no_telp'] ?? null,
            'cabang_id' => $cabangId,
            'role' => $role,                 // label ERD
        ]);
    }

    private function syncRole(User $user, ?string $role): void
    {
        $user->syncRoles($role ? [$role] : []);
    }

    /**
     * Sinkron kecamatan yang ditangani (hanya untuk marketing, dan hanya
     * kecamatan yang berada dalam cabang user). Role non-marketing → dikosongkan.
     */
    private function syncKecamatan(User $user, array $data): void
    {
        if (($data['role'] ?? null) !== 'marketing' || ! $user->cabang_id) {
            $user->kecamatan()->sync([]);

            return;
        }

        $ids = $data['kecamatan_ids'] ?? [];
        $valid = Kecamatan::whereIn('id', $ids)
            ->whereHas('kota', fn ($q) => $q->where('cabang_id', $user->cabang_id))
            ->pluck('id')
            ->all();

        $user->kecamatan()->sync($valid);
    }
}
