<div>
    {{-- Desain tab-kategori (sama untuk storefront, sekolah, & panel staf). --}}
    @php
        $paketAda = $this->paketList->isNotEmpty();
        $kategoriAda = $this->kategoriList->isNotEmpty();
        $defaultTab = $paketAda ? "'paket'" : ($this->kategoriList->first()->id ?? "''");
    @endphp

    <div x-data="{ tab: {{ $defaultTab }}, q: '' }">
        {{-- Header + search --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">Katalog paket</h1>
                <p class="text-sm text-ink-muted">Pilih kategori lalu telusuri paket &amp; produk.</p>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <div class="relative flex-1 sm:w-56 sm:flex-none">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    </span>
                    <input x-model="q" type="search" placeholder="Cari…"
                           class="block w-full min-h-[40px] rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                </div>
                @if ($konteks === 'staf')
                    <x-button :href="$this->keranjangUrl()" variant="secondary" size="sm" wire:navigate class="shrink-0">
                        Keranjang @if ($this->cartCount > 0)<span class="ml-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-brand px-1.5 text-xs text-white">{{ $this->cartCount }}</span>@endif
                    </x-button>
                @endif
            </div>
        </div>

        @if ($paketAda || $kategoriAda)
            {{-- Tab kategori (sembunyi saat mencari) --}}
            <div x-show="q === ''" class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @if ($paketAda)
                    <button type="button" @click="tab = 'paket'"
                            :class="tab === 'paket' ? 'border-brand ring-2 ring-brand/25' : 'border-line hover:border-brand/40'"
                            class="flex w-24 shrink-0 flex-col items-center gap-2 rounded-xl border bg-card p-2.5 text-center transition">
                        <span class="grid h-16 w-full place-items-center rounded-lg bg-brand/5 text-brand">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('gift') }}" /></svg>
                        </span>
                        <span class="text-[11px] font-bold text-ink">Paket</span>
                    </button>
                @endif
                @foreach ($this->kategoriList as $kategori)
                    @php $thumb = optional($kategori->produk->first())->foto; @endphp
                    <button type="button" @click="tab = {{ $kategori->id }}"
                            :class="tab === {{ $kategori->id }} ? 'border-brand ring-2 ring-brand/25' : 'border-line hover:border-brand/40'"
                            class="flex w-24 shrink-0 flex-col items-center gap-2 rounded-xl border bg-card p-2.5 text-center transition">
                        <span class="grid h-16 w-full place-items-center overflow-hidden rounded-lg bg-page">
                            @if ($thumb)
                                <img src="{{ asset('storage/'.$thumb) }}" alt="" class="max-h-full max-w-full object-contain">
                            @else
                                <svg class="h-7 w-7 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-[11px] font-bold leading-tight text-ink">{{ $kategori->nama }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Judul hasil pencarian --}}
            <p x-show="q !== ''" x-cloak class="text-sm text-ink-muted">Hasil pencarian untuk “<span class="font-semibold text-ink" x-text="q"></span>”</p>

            {{-- Grid item (tab-filter saat kosong; nama-filter saat mencari) --}}
            <div x-cloak class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->paketList as $paket)
                    <a data-name="{{ mb_strtolower($paket->nama) }}"
                       x-show="q === '' ? tab === 'paket' : $el.dataset.name.includes(q.toLowerCase())"
                       href="{{ $this->detailUrl('paket', $paket->id) }}" wire:navigate
                       class="flex flex-col overflow-hidden rounded-xl border border-line bg-card transition-colors hover:border-brand/40">
                        <div class="relative grid h-24 place-items-center bg-gradient-to-br from-navy to-navy-hover">
                            <svg class="h-8 w-8 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            <span class="absolute left-2 top-2 rounded bg-brand px-1.5 py-0.5 text-[9px] font-extrabold tracking-wide text-white">PAKET</span>
                        </div>
                        <div class="p-3">
                            <div class="truncate text-sm font-bold text-ink">{{ $paket->nama }}</div>
                            <div class="mt-1 text-sm font-extrabold text-navy">Rp{{ number_format($paket->harga, 0, ',', '.') }}</div>
                        </div>
                    </a>
                @endforeach

                @foreach ($this->kategoriList as $kategori)
                    @foreach ($kategori->produk as $produk)
                        <a data-name="{{ mb_strtolower($produk->nama) }}"
                           x-show="q === '' ? tab === {{ $kategori->id }} : $el.dataset.name.includes(q.toLowerCase())"
                           href="{{ $this->detailUrl('produk', $produk->id) }}" wire:navigate
                           class="flex flex-col overflow-hidden rounded-xl border border-line bg-card transition-colors hover:border-brand/40">
                            <div class="flex aspect-square w-full items-center justify-center overflow-hidden bg-page">
                                @if ($produk->foto)
                                    <img src="{{ asset('storage/'.$produk->foto) }}" alt="" class="max-h-full max-w-full object-contain">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-ink-muted">
                                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <div class="text-sm font-semibold leading-snug text-ink">{{ $produk->nama }}</div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-extrabold text-navy">Rp{{ number_format($produk->harga, 0, ',', '.') }}</span>
                                    @if ($produk->frame)<span class="rounded bg-page px-1.5 py-0.5 text-[9px] font-bold text-ink-muted">{{ $produk->frame }}</span>@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                @endforeach
            </div>
        @else
            <x-card>
                <div class="py-10 text-center text-sm text-ink-muted">Belum ada item katalog yang aktif.</div>
            </x-card>
        @endif
    </div>
</div>
