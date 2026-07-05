<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
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
    public function index(): View
    {
        $users = User::with(['cabang', 'roles'])
            ->orderBy('nama')
            ->orderBy('name')
            ->get();

        return view('pengguna.index', compact('users'));
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

        return redirect()->route('pengguna.index')->with('success', 'Pengguna ditambahkan.');
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

        return redirect()->route('pengguna.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $pengguna->delete();

        return redirect()->route('pengguna.index')->with('success', 'Pengguna dihapus.');
    }

    /**
     * Data pilihan untuk form (cabang & role).
     */
    private function formData(array $extra = []): array
    {
        return array_merge([
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'roleOptions' => Role::orderBy('name')->pluck('name')
                ->mapWithKeys(fn ($name) => [$name => \Illuminate\Support\Str::headline($name)])
                ->all(),
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
}
