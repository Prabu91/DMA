<div>
    @php $sf = in_array($konteks, ['publik', 'sekolah'], true); @endphp
    <a href="{{ $this->indexUrl() }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Kembali ke katalog
    </a>

    @if ($tipe === 'paket')
        @php $paket = $this->paket; @endphp
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-card>
                    <div class="flex items-center gap-2">
                        <x-badge variant="brand">Paket</x-badge>
                        <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">{{ $paket->nama }}</h1>
                    </div>
                    @if ($paket->deskripsi)
                        <p class="mt-2 text-sm text-ink-muted">{{ $paket->deskripsi }}</p>
                    @endif
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="{{ $sf ? 'text-3xl font-extrabold text-navy' : 'text-xl font-bold text-ink' }}">Rp{{ number_format($paket->harga, 0, ',', '.') }}</span>
                        @if ($sf)<span class="text-xs text-ink-muted">/ paket</span>@endif
                    </div>

                    <div class="mt-5">
                        <h2 class="mb-2 text-sm font-medium text-ink">Produk termasuk</h2>
                        <div class="overflow-hidden rounded-lg border border-line">
                            @foreach ($paket->produk as $p)
                                <div class="flex items-center justify-between gap-3 border-b border-line px-3 py-2.5 last:border-b-0">
                                    <span class="text-sm text-ink">{{ $p->nama }}</span>
                                    <span class="text-xs text-ink-muted">Rp{{ number_format($p->harga, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-card>
            </div>
            <div>
                <x-card :title="$sf ? 'Pesan paket' : 'Booking'" class="lg:sticky lg:top-20">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-24">
                            <x-input label="Jumlah" type="number" min="1" wire:model="qty" />
                        </div>
                        <x-button wire:click="tambah">
                            <span wire:loading.remove wire:target="tambah">Tambah ke keranjang</span>
                            <span wire:loading wire:target="tambah">Menambah…</span>
                        </x-button>
                        @unless ($sf)
                            <a href="{{ $this->keranjangUrl() }}" wire:navigate class="pb-2.5 text-sm font-medium text-brand hover:text-brand-hover">Lihat keranjang →</a>
                        @endunless
                    </div>
                    @if ($justAdded)
                        <p class="mt-3 text-sm font-semibold text-status-success">Ditambahkan ke keranjang.</p>
                    @endif
                    <p class="mt-3 text-xs text-ink-muted">Desain per produk dalam paket dipilih pada tahap berikutnya.</p>
                </x-card>
            </div>
        </div>
    @else
        @php $produk = $this->produk; @endphp
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Media --}}
            <div>
                <div class="aspect-square w-full overflow-hidden rounded-xl border border-line bg-page">
                    @if ($produk->foto)
                        <img src="{{ asset('storage/'.$produk->foto) }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-ink-muted">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Info + pilihan --}}
            <div class="lg:col-span-2 space-y-6">
                <x-card>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">{{ $produk->nama }}</h1>
                        @if ($produk->frame)<x-badge variant="neutral">{{ $produk->frame }}</x-badge>@endif
                        <x-badge variant="navy">{{ $produk->kategori?->nama }}</x-badge>
                    </div>
                    @if ($produk->deskripsi)
                        <p class="mt-2 text-sm text-ink-muted">{{ $produk->deskripsi }}</p>
                    @endif
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="{{ $sf ? 'text-3xl font-extrabold text-navy' : 'text-xl font-bold text-ink' }}">Rp{{ number_format($produk->harga, 0, ',', '.') }}</span>
                        <span class="text-xs text-ink-muted">/ item</span>
                    </div>
                </x-card>

                {{-- Opsi ukuran --}}
                @if ($produk->opsi->isNotEmpty())
                    <x-card title="Opsi ukuran">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($produk->opsi as $opsi)
                                <label @class([
                                    'flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-sm',
                                    'border-brand bg-brand/5' => $selectedUkuran === $opsi->nilai_opsi,
                                    'border-line hover:bg-page' => $selectedUkuran !== $opsi->nilai_opsi,
                                ])>
                                    <span class="flex items-center gap-2">
                                        <input type="radio" wire:model.live="selectedUkuran" value="{{ $opsi->nilai_opsi }}" class="text-brand focus:ring-brand/30">
                                        <span class="text-ink">{{ $opsi->nilai_opsi }}</span>
                                    </span>
                                    @if ($opsi->is_wajib)<x-badge variant="danger">Wajib</x-badge>@endif
                                </label>
                            @endforeach
                        </div>
                        @if ($produk->opsi->firstWhere('is_wajib', true))
                            <p class="mt-2 text-xs text-ink-muted">Opsi bertanda “Wajib” harus dipilih saat booking.</p>
                        @endif
                    </x-card>
                @endif

                {{-- Pool desain --}}
                @if ($this->pakaiDesain)
                    <x-card>
                        <x-slot name="title">Pilih desain</x-slot>
                        <x-slot name="actions">
                            @if (count($this->tahunOptions) > 0)
                                <x-select wire:model.live="tahunAjaran" :options="$this->tahunOptions" :selected="$tahunAjaran" class="min-w-[10rem]" />
                            @endif
                        </x-slot>

                        @if ($this->desainPool->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($this->desainPool as $desain)
                                    <label @class([
                                        'cursor-pointer overflow-hidden rounded-lg border',
                                        'border-brand ring-2 ring-brand/30' => $selectedDesain === $desain->id,
                                        'border-line hover:border-brand/40' => $selectedDesain !== $desain->id,
                                    ])>
                                        <input type="radio" wire:model.live="selectedDesain" value="{{ $desain->id }}" class="sr-only">
                                        <div class="aspect-[3/4] w-full bg-page">
                                            @if ($desain->foto_preview)
                                                <img src="{{ asset('storage/'.$desain->foto_preview) }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-ink-muted">
                                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="px-2 py-1.5 text-center text-xs text-ink">{{ $desain->kode }}</div>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-ink-muted">Belum ada desain aktif untuk kategori ini pada tahun ajaran terpilih.</p>
                        @endif
                    </x-card>
                @endif

                <x-card :title="$sf ? 'Pesan' : null">
                    @error('selectedDesain')<p class="mb-2 text-sm text-status-danger">{{ $message }}</p>@enderror
                    @error('selectedUkuran')<p class="mb-2 text-sm text-status-danger">{{ $message }}</p>@enderror

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-32">
                            <x-input label="Jumlah" type="number" min="1" wire:model="qty" hint="Jumlah produk dipesan." />
                        </div>
                        <x-button wire:click="tambah">
                            <span wire:loading.remove wire:target="tambah">Tambah ke keranjang</span>
                            <span wire:loading wire:target="tambah">Menambah…</span>
                        </x-button>
                        @unless ($sf)
                            <a href="{{ $this->keranjangUrl() }}" wire:navigate class="pb-2.5 text-sm font-medium text-brand hover:text-brand-hover">Lihat keranjang →</a>
                        @endunless
                    </div>

                    @if ($justAdded)
                        <p class="mt-3 text-sm font-semibold text-status-success">Ditambahkan ke keranjang.</p>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</div>
