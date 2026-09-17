<div>
    @php $sf = in_array($konteks, ['publik', 'sekolah'], true); @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
        <div>
            <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">Keranjang</h1>
            <p class="text-sm text-ink-muted">Periksa item, jumlah siswa, lalu lanjut memesan.</p>
        </div>
        <a href="{{ $this->katalogUrl() }}" wire:navigate class="shrink-0 whitespace-nowrap pt-1 text-sm font-bold text-brand hover:text-brand-hover">+ Tambah item</a>
    </div>

    @if ($info)
        <div class="mb-4 rounded-lg border border-status-info/20 bg-status-info/10 px-4 py-3 text-sm text-status-info">{{ $info }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Booking untuk — konteks publik: sekolah ditentukan saat login/checkout (Fase 4). --}}
            @if ($konteks !== 'publik')
            <x-card title="Booking untuk">
                @if ($this->induk)
                    {{-- Mode susulan: sekolah dikunci ke sekolah order induk --}}
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$this->induk->sekolah?->nama" size="sm" />
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-ink">{{ $this->induk->sekolah?->nama }}</span>
                                    <x-badge variant="brand">Susulan</x-badge>
                                </div>
                                <div class="text-xs text-ink-muted">
                                    Susulan dari
                                    <a href="{{ route('app.order.show', $this->induk->id) }}" wire:navigate class="font-medium text-brand hover:text-brand-hover">{{ $this->induk->booking_code ?? 'order #'.$this->induk->id }}</a>
                                </div>
                            </div>
                        </div>
                        <x-confirm action="batalSusulan" title="Batalkan order susulan"
                                   message="Keranjang akan dikosongkan dan Anda kembali ke order induk. Lanjutkan?"
                                   confirm-label="Ya, batalkan" variant="ghost" confirm-variant="danger" size="sm">Batalkan susulan</x-confirm>
                    </div>
                    <p class="mt-3 text-xs text-ink-muted">
                        Produk yang juga ada di order induk memakai <span class="font-medium text-ink">harga induk</span>.
                        Order susulan tidak mendapat item free dan langsung ke hari event, tanpa H-7/H-2.
                    </p>
                @elseif ($this->isSekolahFlow)
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$this->sekolahTerpilih?->nama" size="sm" />
                        <div>
                            <div class="text-sm font-medium text-ink">{{ $this->sekolahTerpilih?->nama }}</div>
                            <div class="text-xs text-ink-muted">{{ $this->sekolahTerpilih?->id_sekolah }} · Booking mandiri</div>
                        </div>
                    </div>
                @else
                    <x-select label="Sekolah (di cabang Anda)" wire:model.live="sekolahId" :options="$this->sekolahOptions" :selected="$sekolahId" placeholder="— Pilih sekolah —" />
                    @if ($this->sekolahTerpilih)
                        <p class="mt-2 text-xs text-ink-muted">{{ $this->sekolahTerpilih->id_sekolah }} · dibuat oleh marketing</p>
                    @endif
                @endif
            </x-card>
            @endif

            {{-- Item --}}
            <x-card title="Item" padding="p-0">
                @forelse ($this->lines as $line)
                    <div wire:key="line-{{ $line['key'] }}" class="flex flex-col gap-3 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :variant="$line['tipe'] === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($line['tipe']) }}</x-badge>
                                <span class="text-sm {{ $sf ? 'font-bold' : 'font-medium' }} text-ink">{{ $line['nama'] }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-muted">
                                @if ($line['desain']) Desain {{ $line['desain'] }} · @endif
                                @if ($line['ukuran']) Ukuran {{ $line['ukuran'] }} · @endif
                                <x-harga :nilai="$line['unit']" satuan="/item" />
                                @if (! empty($line['harga_induk']))<span class="font-medium text-brand">· harga order induk</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3 sm:shrink-0 sm:justify-end">
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="ubahQty('{{ $line['key'] }}', {{ $line['qty'] - 1 }})" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink hover:bg-page">−</button>
                                <span class="w-8 text-center text-sm text-ink">{{ $line['qty'] }}</span>
                                <button type="button" wire:click="ubahQty('{{ $line['key'] }}', {{ $line['qty'] + 1 }})" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-ink hover:bg-page">+</button>
                            </div>
                            <x-harga :nilai="$line['total']" class="block text-right text-sm font-medium text-ink sm:w-24" />
                            <button type="button" wire:click="hapus('{{ $line['key'] }}')" class="shrink-0 text-ink-muted hover:text-status-danger" title="Hapus">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-ink-muted">Keranjang masih kosong.</p>
                        <a href="{{ $this->katalogUrl() }}" wire:navigate class="mt-1 inline-block text-sm font-medium text-brand hover:text-brand-hover">Jelajahi katalog</a>
                    </div>
                @endforelse
            </x-card>
        </div>

        {{-- Ringkasan --}}
        <div>
            <x-card title="Ringkasan">
                <div class="space-y-4">
                    @if ($konteks !== 'publik')
                        <x-input :label="$this->induk ? 'Jumlah siswa susulan' : 'Jumlah siswa'" type="number" min="0" wire:model.live.debounce.400ms="jumlahSiswa"
                                 :hint="$this->induk ? 'Jumlah anak yang difoto di sesi susulan.' : 'Dipakai untuk aturan free sekolah.'" />
                    @endif

                    <div @class(['flex items-baseline justify-between', 'border-t border-line pt-4' => $konteks !== 'publik'])>
                        <span class="text-sm text-ink-muted">Subtotal</span>
                        <x-harga :nilai="$this->subtotal" class="{{ $sf ? 'text-xl font-extrabold text-navy' : 'text-sm font-medium text-ink' }}" />
                    </div>
                    <p class="text-xs text-ink-muted">Item free &amp; total akhir dihitung pada tahap review.</p>

                    @if ($konteks === 'publik')
                        <x-button wire:click="lanjut" class="w-full">Lanjut ke pemesanan</x-button>
                        <p class="text-center text-xs text-ink-muted">Masuk atau daftar untuk menyelesaikan pesanan.</p>
                    @else
                        <x-button wire:click="lanjut" class="w-full">Lanjut ke review</x-button>
                    @endif

                    @if (! app(\App\Support\Cart::class)->isEmpty())
                        <x-confirm action="kosongkan" block variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, kosongkan"
                                   title="Kosongkan keranjang" message="Semua item di keranjang akan dihapus. Lanjutkan?"
                                   trigger-class="w-full text-xs text-ink-muted hover:text-status-danger">Kosongkan keranjang</x-confirm>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
