{{-- Form pengguna dipakai create & edit. Variabel: $pengguna (opsional), $cabangOptions, $roleOptions --}}
@php $pengguna = $pengguna ?? null; @endphp

<x-card>
    <form method="POST" action="{{ $pengguna ? route('app.pengguna.update', $pengguna) : route('app.pengguna.store') }}" class="max-w-lg space-y-4">
        @csrf
        @if ($pengguna)
            @method('PATCH')
        @endif

        <x-input name="nama" label="Nama lengkap" :value="old('nama', $pengguna?->nama ?? $pengguna?->name)" required autofocus autocomplete="name" />

        <x-input name="email" label="Email" type="email" :value="old('email', $pengguna?->email)" required autocomplete="off" />

        <x-input name="no_telp" label="No. telepon" :value="old('no_telp', $pengguna?->no_telp)" hint="Opsional." placeholder="08xxxxxxxxxx" />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-select
                name="role"
                label="Peran"
                :options="$roleOptions"
                :selected="old('role', $pengguna?->getRoleNames()->first())"
                placeholder="— Tanpa peran —"
                hint="Menentukan akses."
            />
            <x-select
                name="cabang_id"
                label="Cabang"
                :options="$cabangOptions"
                :selected="old('cabang_id', $pengguna?->cabang_id)"
                placeholder="— Tanpa cabang —"
                hint="Diabaikan untuk super admin & operasional."
            />
        </div>

        <div class="border-t border-line pt-4">
            <p class="mb-3 text-sm font-medium text-ink">
                {{ $pengguna ? 'Ubah kata sandi' : 'Kata sandi' }}
            </p>
            <div class="space-y-4">
                <x-input
                    name="password"
                    label="Kata sandi{{ $pengguna ? ' baru' : '' }}"
                    type="password"
                    :required="! $pengguna"
                    autocomplete="new-password"
                    :hint="$pengguna ? 'Kosongkan jika tidak diubah.' : 'Minimal 8 karakter.'"
                />
                <x-input
                    name="password_confirmation"
                    label="Ulangi kata sandi"
                    type="password"
                    :required="! $pengguna"
                    autocomplete="new-password"
                />
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-button type="submit">{{ $pengguna ? 'Simpan perubahan' : 'Simpan' }}</x-button>
            <x-button :href="route('app.pengguna.index')" variant="ghost">Batal</x-button>
        </div>
    </form>
</x-card>
