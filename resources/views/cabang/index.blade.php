<x-app-layout>
    <x-slot name="header">
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'url' => route('app.dashboard')],
            ['label' => 'Cabang'],
        ]" />
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-medium text-ink">Cabang</h1>
                <p class="text-sm text-ink-muted">Kelola daftar cabang DMA.</p>
            </div>
            <x-button :href="route('app.cabang.create')" size="sm">Tambah cabang</x-button>
        </div>
    </x-slot>

    <x-card padding="p-0">
        @forelse ($cabang as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="flex min-w-0 items-center gap-3">
                    <x-avatar :name="$item->nama" size="sm" />
                    <div class="min-w-0">
                        <div class="truncate text-sm text-ink">{{ $item->nama }}</div>
                        <div class="truncate text-xs text-ink-muted">
                            {{ $item->kode_area ?: '—' }}
                            · {{ $item->users_count }} pengguna
                            · {{ $item->sekolah_count }} sekolah
                            · {{ $item->orders_count }} order
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <x-button :href="route('app.cabang.edit', $item)" variant="secondary" size="sm">Ubah</x-button>
                    <form method="POST" action="{{ route('app.cabang.destroy', $item) }}"
                          onsubmit="return confirm('Hapus cabang {{ $item->nama }}?')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost" size="sm">Hapus</x-button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada cabang.</div>
        @endforelse
    </x-card>
</x-app-layout>
