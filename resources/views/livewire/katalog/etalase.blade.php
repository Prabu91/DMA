<div>
    <div class="mb-6 flex items-start justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Katalog</h1>
            <p class="text-sm text-ink-muted">Pilih paket atau produk satuan untuk memulai booking.</p>
        </div>
        <x-button :href="$this->keranjangUrl()" variant="secondary" size="sm" wire:navigate>
            Keranjang @if ($this->cartCount > 0)<span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-brand px-1.5 text-xs text-white">{{ $this->cartCount }}</span>@endif
        </x-button>
    </div>

    <div class="mb-6">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari paket atau produk…" />
    </div>

    {{-- Paket --}}
    @if ($this->paketList->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-medium text-ink">Paket</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->paketList as $paket)
                    <a href="{{ $this->detailUrl('paket', $paket->id) }}" wire:navigate
                       class="flex flex-col rounded-xl border border-line bg-card p-4 transition-colors hover:border-brand/40">
                        <div class="flex items-center justify-between">
                            <x-badge variant="brand">Paket</x-badge>
                            <span class="text-xs text-ink-muted">{{ $paket->produk_count }} produk</span>
                        </div>
                        <div class="mt-3 text-sm font-medium text-ink">{{ $paket->nama }}</div>
                        @if ($paket->deskripsi)
                            <div class="mt-1 line-clamp-2 text-xs text-ink-muted">{{ $paket->deskripsi }}</div>
                        @endif
                        <div class="mt-3 text-base font-medium text-ink">Rp{{ number_format($paket->harga, 0, ',', '.') }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Produk per kategori --}}
    @forelse ($this->kategoriList as $kategori)
        <section class="mb-8">
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-sm font-medium text-ink">{{ $kategori->nama }}</h2>
                @if ($kategori->pakai_desain)
                    <x-badge variant="navy">Berdesain</x-badge>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($kategori->produk as $produk)
                    <a href="{{ $this->detailUrl('produk', $produk->id) }}" wire:navigate
                       class="flex flex-col overflow-hidden rounded-xl border border-line bg-card transition-colors hover:border-brand/40">
                        <div class="aspect-square w-full bg-page">
                            @if ($produk->foto)
                                <img src="{{ asset('storage/'.$produk->foto) }}" alt="" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-ink-muted">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                                </div>
                            @endif
                        </div>
                        <div class="p-3">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate text-sm text-ink">{{ $produk->nama }}</span>
                                @if ($produk->gaya)<x-badge variant="neutral">{{ $produk->gaya }}</x-badge>@endif
                            </div>
                            <div class="mt-1 text-sm font-medium text-ink">Rp{{ number_format($produk->harga, 0, ',', '.') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        @if ($this->paketList->isEmpty())
            <x-card>
                <div class="py-10 text-center text-sm text-ink-muted">Belum ada item katalog yang aktif.</div>
            </x-card>
        @endif
    @endforelse
</div>
