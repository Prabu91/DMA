<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-medium text-ink">Pengguna</h1>
                <p class="text-sm text-ink-muted">Kelola akun, cabang, dan peran.</p>
            </div>
            <x-button :href="route('pengguna.create')" size="sm">Tambah pengguna</x-button>
        </div>
    </x-slot>

    <x-card padding="p-0">
        @forelse ($users as $u)
            @php
                $role = $u->getRoleNames()->first();
                $roleLabel = $role ? \Illuminate\Support\Str::headline($role) : null;
                $cabangLabel = $u->seesAllCabang() ? 'Semua cabang' : ($u->cabang?->nama ?? 'Tanpa cabang');
            @endphp
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="flex min-w-0 items-center gap-3">
                    <x-avatar :name="$u->nama ?? $u->name" size="sm" />
                    <div class="min-w-0">
                        <div class="truncate text-sm text-ink">{{ $u->nama ?? $u->name }}</div>
                        <div class="truncate text-xs text-ink-muted">
                            {{ $u->email }}@if ($u->no_telp) · {{ $u->no_telp }}@endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @if ($roleLabel)
                                <x-badge variant="brand">{{ $roleLabel }}</x-badge>
                            @else
                                <x-badge variant="neutral">Tanpa peran</x-badge>
                            @endif
                            <x-badge variant="navy">{{ $cabangLabel }}</x-badge>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <x-button :href="route('pengguna.edit', $u)" variant="secondary" size="sm">Ubah</x-button>
                    @unless ($u->is(auth()->user()))
                        <form method="POST" action="{{ route('pengguna.destroy', $u) }}"
                              onsubmit="return confirm('Hapus pengguna {{ $u->nama ?? $u->name }}?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm">Hapus</x-button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada pengguna.</div>
        @endforelse
    </x-card>
</x-app-layout>
