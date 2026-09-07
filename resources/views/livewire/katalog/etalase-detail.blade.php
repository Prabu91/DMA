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
                        <x-harga :nilai="$paket->harga" :satuan="$sf ? '/ paket' : null" class="{{ $sf ? 'text-3xl font-extrabold text-navy' : 'text-xl font-bold text-ink' }}" />
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
                            <a href="{{ $this->keranjangUrl() }}" wire:navigate class="flex min-h-[44px] items-center text-sm font-medium text-brand hover:text-brand-hover">Lihat keranjang →</a>
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
            {{-- Media: begitu desain dipilih, pratinjaunya menggantikan foto produk
                 supaya desainnya terlihat besar sebelum dipesan. --}}
            @php
                $desainTerpilih = $selectedDesain ? $this->desainPool->firstWhere('id', $selectedDesain) : null;
                $fotoPratinjau = $desainTerpilih?->foto_preview ?: $produk->foto;
            @endphp
            <div>
                <div class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-xl border border-line bg-page p-2">
                    @if ($fotoPratinjau)
                        {{-- object-contain: tampil utuh mengikuti orientasi asli (potret/lanskap), tak dipotong --}}
                        <img src="{{ asset('storage/'.$fotoPratinjau) }}" alt="" class="max-h-full max-w-full object-contain">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-ink-muted">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                        </div>
                    @endif
                </div>
                @if ($desainTerpilih)
                    <p class="mt-2 text-center text-xs text-ink-muted">
                        Pratinjau desain <span class="font-medium text-ink">{{ $desainTerpilih->kode }}</span>
                    </p>
                @endif
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
                    {{-- Harga ikut pilihan varian, bukan harga dasar — angkanya sama
                         dengan yang dihitung keranjang & order. --}}
                    <div class="mt-4 flex flex-wrap items-baseline gap-2">
                        <x-harga :nilai="$this->hargaEfektif" satuan="/ item" class="{{ $sf ? 'text-3xl font-extrabold text-navy' : 'text-xl font-bold text-ink' }}" />
                        {{-- Harga coret hanya bila harga memang boleh dilihat, agar tidak bocor. --}}
                        @if ($this->hargaEfektif !== (int) $produk->harga && \App\Support\Pengaturan::bolehLihatHarga())
                            <span class="text-xs text-ink-muted line-through">Rp{{ number_format($produk->harga, 0, ',', '.') }}</span>
                            <span class="text-xs text-ink-muted">· mengikuti pilihan varian</span>
                        @endif
                    </div>
                </x-card>

                {{-- Mode Pas Foto: satu harga, jatah pcs dibagi ke beberapa ukuran. --}}
                @if ($this->pakaiKomposisi)
                    <x-card>
                        <x-slot name="title">Ukuran</x-slot>
                        <x-slot name="subtitle">Bagi jatah maksimal {{ $this->maksPcs() }} pcs ke ukuran yang diinginkan. Harganya tetap sama.</x-slot>

                        <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line">
                            @foreach ($this->ukuranKomposisi as $ukuran)
                                @php $jumlah = (int) ($komposisi[$ukuran] ?? 0); @endphp
                                <li wire:key="pcs-{{ $loop->index }}" class="flex items-center justify-between gap-3 p-3">
                                    <span class="text-sm font-medium text-ink">{{ $ukuran }}</span>
                                    <span class="flex items-center gap-2">
                                        <button type="button" wire:click="ubahPcs(@js($ukuran), -1)" @disabled($jumlah < 1) aria-label="Kurangi {{ $ukuran }}"
                                                class="grid h-9 w-9 place-items-center rounded-lg border border-line text-ink transition hover:border-brand/60 disabled:opacity-40">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" d="M5 12h14" /></svg>
                                        </button>
                                        <input type="number" min="0" max="{{ $this->maksPcs() }}" wire:model.live.debounce.400ms="komposisi.{{ $ukuran }}"
                                               class="h-9 w-16 rounded-lg border border-line bg-card text-center text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                                        <button type="button" wire:click="ubahPcs(@js($ukuran), 1)" @disabled($this->totalPcs >= $this->maksPcs()) aria-label="Tambah {{ $ukuran }}"
                                                class="grid h-9 w-9 place-items-center rounded-lg border border-line text-ink transition hover:border-brand/60 disabled:opacity-40">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                                        </button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm {{ $this->totalPcs > $this->maksPcs() ? 'text-status-danger' : 'text-ink-muted' }}">
                                <span class="font-semibold text-ink">{{ $this->totalPcs }}</span> / {{ $this->maksPcs() }} pcs terpakai
                            </p>
                            @if ($this->totalPcs > 0)
                                @php
                                    // Dirangkai di PHP: $loop->last ikut menghitung ukuran
                                    // yang jatahnya 0, sehingga separatornya menggantung.
                                    $ringkas = [];
                                    foreach ($this->ukuranKomposisi as $u) {
                                        if (! empty($komposisi[$u])) {
                                            $ringkas[] = $u.' ×'.$komposisi[$u];
                                        }
                                    }
                                @endphp
                                <p class="text-xs text-ink-muted">{{ implode(' · ', $ringkas) }}</p>
                            @endif
                        </div>

                        @error('komposisi')<p class="mt-2 text-sm text-status-danger">{{ $message }}</p>@enderror
                    </x-card>
                @endif

                {{-- Satu kartu per tipe varian: judulnya ikut nama varian (Opsi Box, Opsi Ukuran, ...) --}}
                @foreach ($this->variantGroups as $tipeVarian => $nilaiVarian)
                    <x-card wire:key="varian-{{ $loop->index }}" title="Opsi {{ \App\Livewire\Katalog\EtalaseDetail::labelVarian($tipeVarian) }}">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($nilaiVarian as $opsi)
                                @php $terpilih = ($pilihan[$tipeVarian] ?? null) === $opsi->nilai_opsi; @endphp
                                <label @class([
                                    'flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-sm',
                                    'border-brand bg-brand/5' => $terpilih,
                                    'border-line hover:bg-page' => ! $terpilih,
                                ])>
                                    <span class="flex items-center gap-2">
                                        <input type="radio" wire:model.live="pilihan.{{ $tipeVarian }}" value="{{ $opsi->nilai_opsi }}" class="text-brand focus:ring-brand/30">
                                        <span class="text-ink">{{ $opsi->nilai_opsi }}</span>
                                    </span>
                                    @if ($opsi->is_wajib)<x-badge variant="danger">Wajib</x-badge>@endif
                                </label>
                            @endforeach
                        </div>
                        @error('pilihan.'.$tipeVarian)
                            <p class="mt-2 text-sm text-status-danger">{{ $message }}</p>
                        @enderror
                        @if ($nilaiVarian->firstWhere('is_wajib', true))
                            <p class="mt-2 text-xs text-ink-muted">Opsi bertanda “Wajib” harus dipilih saat booking.</p>
                        @endif
                    </x-card>
                @endforeach

                {{-- Pool desain --}}
                @if ($this->pakaiDesain)
                    <x-card>
                        <x-slot name="title">Pilih desain @if ($this->selectedUkuran)<span class="text-sm font-normal text-ink-muted">· untuk ukuran {{ $this->selectedUkuran }}</span>@endif</x-slot>
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
                                        <div class="flex aspect-square w-full items-center justify-center bg-page p-1">
                                            @if ($desain->foto_preview)
                                                {{-- object-contain: preview mengikuti orientasi foto asli (potret/lanskap) --}}
                                                <img src="{{ asset('storage/'.$desain->foto_preview) }}" alt="" class="max-h-full max-w-full object-contain">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-ink-muted">
                                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="px-2 py-1.5 text-center text-xs text-ink">{{ $desain->kode }}@if ($desain->ukuran)<span class="ml-1 text-ink-muted">· {{ $desain->ukuran }}</span>@endif</div>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-ink-muted">
                                @if ($this->selectedUkuran)
                                    Belum ada desain untuk ukuran {{ $this->selectedUkuran }} pada tahun ajaran terpilih.
                                @else
                                    Belum ada desain aktif untuk kategori ini pada tahun ajaran terpilih.
                                @endif
                            </p>
                        @endif
                    </x-card>
                @endif

                <x-card :title="$sf ? 'Pesan' : null">
                    @error('selectedDesain')<p class="mb-2 text-sm text-status-danger">{{ $message }}</p>@enderror

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-32">
                            <x-input label="Jumlah" type="number" min="1" wire:model="qty" />
                        </div>
                        <x-button wire:click="tambah">
                            <span wire:loading.remove wire:target="tambah">Tambah ke keranjang</span>
                            <span wire:loading wire:target="tambah">Menambah…</span>
                        </x-button>
                        @unless ($sf)
                            <a href="{{ $this->keranjangUrl() }}" wire:navigate class="flex min-h-[44px] items-center text-sm font-medium text-brand hover:text-brand-hover">Lihat keranjang →</a>
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
