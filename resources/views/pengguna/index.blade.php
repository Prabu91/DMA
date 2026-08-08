<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-medium text-ink">Pengguna</h1>
                <p class="text-sm text-ink-muted">Kelola akun, cabang, dan peran.</p>
            </div>
            <x-button :href="route('app.pengguna.create')" size="sm">Tambah pengguna</x-button>
        </div>
    </x-slot>

    {{-- Filter cabang + role --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        @php $ctrl = 'h-9 rounded-lg border border-line bg-card px-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
        <select name="cabang" onchange="this.form.submit()" class="{{ $ctrl }}">
            <option value="">Semua cabang</option>
            @foreach ($cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($filterCabang === (string) $id)>{{ $nama }}</option>@endforeach
        </select>
        <select name="role" onchange="this.form.submit()" class="{{ $ctrl }}">
            <option value="">Semua peran</option>
            @foreach ($roleOptions as $val => $label)<option value="{{ $val }}" @selected($filterRole === (string) $val)>{{ $label }}</option>@endforeach
        </select>
        @if ($filterCabang !== '' || $filterRole !== '')
            <a href="{{ route('app.pengguna.index') }}" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand hover:bg-brand/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>Reset
            </a>
        @endif
        <span class="ml-auto text-xs text-ink-muted">{{ $users->count() }} pengguna</span>
    </form>

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
                    <x-button :href="route('app.pengguna.edit', $u)" variant="secondary" size="sm">Ubah</x-button>
                    @unless ($u->is(auth()->user()))
                        <form method="POST" action="{{ route('app.pengguna.destroy', $u) }}"
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
