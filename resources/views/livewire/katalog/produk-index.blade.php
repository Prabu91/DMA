<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Katalog'],
        ['label' => 'Produk'],
    ]" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-medium text-ink">Produk</h1>
            <p class="text-sm text-ink-muted">Katalog global — berlaku untuk semua cabang.</p>
        </div>
        <x-button :href="route('app.produk.create')" size="sm" wire:navigate class="shrink-0 self-start whitespace-nowrap sm:self-auto">Tambah produk</x-button>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    <div class="mb-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari produk…" />
    </div>

    <x-card padding="p-0">
        @forelse ($produk as $item)
            <div class="flex flex-col gap-2 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    @if ($item->foto)
                        <img src="{{ asset('storage/'.$item->foto) }}" alt="" class="h-12 w-12 shrink-0 rounded-lg border border-line object-cover">
                    @else
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-line bg-page text-ink-muted">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-sm font-medium text-ink">{{ $item->nama }}</span>
                            @if ($item->gaya)<x-badge variant="neutral">{{ $item->gaya }}</x-badge>@endif
                            <x-badge :variant="$item->status === 'aktif' ? 'success' : 'danger'">{{ \App\Models\Produk::STATUS[$item->status] ?? $item->status }}</x-badge>
                        </div>
                        <div class="mt-0.5 text-xs text-ink-muted">
                            {{ $item->kategori?->nama ?? '—' }}
                            · Rp{{ number_format($item->harga, 0, ',', '.') }}
                            · {{ $item->opsi_count }} opsi · {{ $item->bonus_count }} bonus
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 pl-[3.75rem] sm:pl-0">
                    <x-button :href="route('app.produk.edit', $item)" variant="secondary" size="sm" wire:navigate>Ubah</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus produk {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada produk.</div>
        @endforelse
    </x-card>
</div>
