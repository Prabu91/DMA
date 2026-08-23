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
        @php $ctrl = 'h-9 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
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
        <span class="ml-auto text-xs text-ink-muted">{{ $users->total() }} pengguna</span>
    </form>

    <x-table min-width="820px">
        <x-slot:head>
            <x-table.th>Nama</x-table.th>
            <x-table.th>Kontak</x-table.th>
            <x-table.th>Peran</x-table.th>
            <x-table.th>Cabang</x-table.th>
            <x-table.th align="right">Aksi</x-table.th>
        </x-slot:head>

        @forelse ($users as $u)
            @php
                $role = $u->getRoleNames()->first();
                $roleLabel = $role ? \Illuminate\Support\Str::headline($role) : null;
                $cabangLabel = $u->seesAllCabang() ? 'Semua cabang' : ($u->cabang?->nama ?? 'Tanpa cabang');
            @endphp
            <x-table.tr>
                <x-table.td>
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$u->nama ?? $u->name" size="sm" />
                        <span class="font-medium text-ink">{{ $u->nama ?? $u->name }}</span>
                    </div>
                </x-table.td>
                <x-table.td muted>
                    <div class="text-ink">{{ $u->email }}</div>
                    @if ($u->no_telp)<div class="text-xs text-ink-muted">{{ $u->no_telp }}</div>@endif
                </x-table.td>
                <x-table.td>
                    @if ($roleLabel)<x-badge variant="brand">{{ $roleLabel }}</x-badge>@else<x-badge variant="neutral">Tanpa peran</x-badge>@endif
                </x-table.td>
                <x-table.td><x-badge variant="navy">{{ $cabangLabel }}</x-badge></x-table.td>
                <x-table.td align="right" nowrap>
                    <x-button :href="route('app.pengguna.edit', $u)" variant="secondary" size="sm">Ubah</x-button>
                    @unless ($u->is(auth()->user()))
                        <form method="POST" action="{{ route('app.pengguna.destroy', $u) }}" class="inline"
                              onsubmit="return confirm('Hapus pengguna {{ $u->nama ?? $u->name }}?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm">Hapus</x-button>
                        </form>
                    @endunless
                </x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="5">Belum ada pengguna.</x-table.empty>
        @endforelse
    </x-table>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
