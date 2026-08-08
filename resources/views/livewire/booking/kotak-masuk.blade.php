<div>
    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Kotak masuk</h1>
        <p class="text-sm text-ink-muted">Order booking mandiri sekolah yang menunggu penugasan marketing.</p>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    {{-- Tab --}}
    <div class="mb-4 inline-flex rounded-lg border border-line bg-card p-1 text-sm">
        <button type="button" wire:click="$set('tampil', 'baru')"
            @class(['rounded-md px-3 py-1.5', 'bg-brand text-white' => $tampil === 'baru', 'text-ink-muted hover:text-ink' => $tampil !== 'baru'])>
            Belum ditugaskan
        </button>
        <button type="button" wire:click="$set('tampil', 'ditugaskan')"
            @class(['rounded-md px-3 py-1.5', 'bg-brand text-white' => $tampil === 'ditugaskan', 'text-ink-muted hover:text-ink' => $tampil !== 'ditugaskan'])>
            Sudah ditugaskan
        </button>
    </div>

    {{-- Strip per-cabang (admin) --}}
    @if ($this->isAdmin && $this->cabangList->isNotEmpty())
        @include('booking.partials.cabang-strip', ['cabangs' => $this->cabangList, 'counts' => $this->cabangCounts, 'aktif' => $cabangId, 'total' => $this->cabangCounts->sum()])
    @endif

    {{-- Filter (ringkas) --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[180px] flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="q" type="search" placeholder="Cari kode / nama sekolah…"
                   class="h-9 w-full rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        </div>
        <div class="flex h-9 items-center gap-1.5 rounded-lg border border-line bg-card px-2.5">
            <span class="text-xs text-ink-muted">Masuk</span>
            <input wire:model.live="dari" type="date" title="Masuk dari" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" title="Masuk sampai" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>
        @if ($q !== '' || $dari !== '' || $sampai !== '' || $cabangId !== '')
            <button type="button" wire:click="resetFilter" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand hover:bg-brand/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Reset
            </button>
        @endif
    </div>

    <x-card padding="p-0">
        @forelse ($this->orders as $order)
            <div wire:key="order-{{ $order->id }}" class="flex flex-col gap-3 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-ink">Booking #{{ $order->id }}</span>
                        <span class="text-sm text-ink">· {{ $order->sekolah?->nama }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-muted">
                        {{ $order->cabang?->nama }} ·
                        {{ optional($order->tanggal_booking)->translatedFormat('d M Y') }} ·
                        {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa ·
                        Rp{{ number_format($order->total, 0, ',', '.') }}
                        @if ($tampil === 'ditugaskan')
                            · Marketing: <span class="text-ink">{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="lihatDetail({{ $order->id }})" variant="ghost" size="sm">Detail</x-button>

                    @if ($tampil === 'baru' && $this->isMarketing)
                        <x-button wire:click="ambil({{ $order->id }})" wire:confirm="Ambil order ini untuk Anda?" size="sm">Ambil</x-button>
                    @endif

                    @if ($this->isAdmin)
                        @php $opsi = $this->marketingByCabang[$order->cabang_id] ?? []; @endphp
                        <select wire:model="pilihMarketing.{{ $order->id }}"
                                class="min-h-[36px] rounded-lg border border-line bg-card px-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30">
                            <option value="">— Marketing —</option>
                            @foreach ($opsi as $uid => $nama)
                                <option value="{{ $uid }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        @if ($tampil === 'baru')
                            <x-button wire:click="tugaskan({{ $order->id }})" wire:confirm="Tugaskan order ini ke marketing terpilih?" variant="secondary" size="sm">Tugaskan</x-button>
                        @else
                            <x-button wire:click="reassign({{ $order->id }})" wire:confirm="Ubah penugasan marketing order ini?" variant="secondary" size="sm">Ubah</x-button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">
                {{ $tampil === 'baru' ? 'Tidak ada order yang menunggu penugasan.' : 'Belum ada order yang ditugaskan.' }}
            </div>
        @endforelse
    </x-card>

    {{-- Modal detail order --}}
    @if ($this->detailOrder)
        @php $d = $this->detailOrder; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
             wire:key="modal-{{ $d->id }}" x-data x-on:keydown.escape.window="$wire.tutupDetail()">
            <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-card shadow-2xl ring-1 ring-black/5 sm:rounded-2xl"
                 x-on:click.outside="$wire.tutupDetail()">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-3 bg-gradient-to-br from-navy to-navy-hover px-5 py-4 text-white">
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-white/60">Detail order masuk</div>
                        <div class="mt-0.5 truncate text-lg font-extrabold tracking-tight">{{ $d->booking_code ?? 'Booking #'.$d->id }}</div>
                        <div class="mt-0.5 text-xs text-white/70">Masuk {{ optional($d->tanggal_booking)->translatedFormat('d M Y · H:i') }}</div>
                    </div>
                    <button type="button" wire:click="tutupDetail" class="rounded-lg p-1 text-white/70 transition-colors hover:bg-white/10 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    {{-- Sekolah --}}
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$d->sekolah?->nama" size="sm" />
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-ink">{{ $d->sekolah?->nama ?? '—' }}</div>
                            <div class="truncate text-xs text-ink-muted">{{ $d->sekolah?->id_sekolah }} · {{ $d->cabang?->nama }}</div>
                        </div>
                    </div>

                    {{-- Ringkasan angka --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-line bg-page/50 px-3 py-2.5 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-ink-muted">Siswa</div>
                            <div class="mt-0.5 text-base font-extrabold text-ink">{{ $d->jumlah_siswa ?? '—' }}</div>
                        </div>
                        <div class="rounded-xl border border-line bg-page/50 px-3 py-2.5 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-ink-muted">Item</div>
                            <div class="mt-0.5 text-base font-extrabold text-ink">{{ $d->items->count() }}</div>
                        </div>
                        <div class="rounded-xl border border-line bg-page/50 px-3 py-2.5 text-center">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-ink-muted">Total</div>
                            <div class="mt-0.5 text-sm font-extrabold text-navy">Rp{{ number_format($d->total, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    {{-- Jadwal --}}
                    <div class="flex items-center gap-2 rounded-xl border border-line px-3 py-2.5 text-sm">
                        <svg class="h-4 w-4 shrink-0 text-brand" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('calendar') }}" /></svg>
                        <span class="text-ink">{{ $d->tanggal_event ? $d->tanggal_event->translatedFormat('l, d M Y') : 'Tanggal event belum diisi' }}</span>
                        @if ($d->jam_event)<span class="text-ink-muted">· {{ $d->jam_event }}</span>@endif
                    </div>

                    {{-- Item --}}
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">Rincian item</div>
                        <div class="divide-y divide-line overflow-hidden rounded-xl border border-line">
                            @forelse ($d->items as $item)
                                <div class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm">
                                    <span class="flex min-w-0 items-center gap-1.5">
                                        <span class="truncate text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                                        @if ($item->desain)<span class="rounded bg-page px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">{{ $item->desain->kode }}</span>@endif
                                        @if ($item->opsi_ukuran)<span class="rounded bg-page px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">{{ $item->opsi_ukuran }}</span>@endif
                                        @if ($item->is_free)<span class="rounded bg-status-success/10 px-1.5 py-0.5 text-[10px] font-medium text-status-success">gratis</span>@endif
                                    </span>
                                    <span class="shrink-0 text-xs font-medium text-ink-muted">×{{ $item->qty }}</span>
                                </div>
                            @empty
                                <div class="px-3 py-4 text-center text-xs text-ink-muted">Tak ada item.</div>
                            @endforelse
                        </div>
                    </div>

                    @if ($d->marketing)
                        <div class="text-xs text-ink-muted">Marketing: <span class="font-medium text-ink">{{ $d->marketing->nama ?? $d->marketing->name }}</span></div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-2 border-t border-line bg-page/40 px-5 py-3">
                    @if ($tampil === 'baru' && $this->isMarketing)
                        <x-button wire:click="ambil({{ $d->id }})" wire:confirm="Ambil order ini untuk Anda?" size="sm">Ambil order ini</x-button>
                    @endif
                    <x-button wire:click="tutupDetail" variant="ghost" size="sm">Tutup</x-button>
                </div>
            </div>
        </div>
    @endif
</div>
