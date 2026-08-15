<div>
    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">All Data Sales</h1>
        <p class="text-sm text-ink-muted">Semua order ter-assign (non-batal) + rincian finansial. Klik kategori untuk lihat produk.</p>
    </div>

    {{-- Filter --}}
    @php $ctrl = 'h-9 rounded-lg border border-line bg-card px-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[200px] flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="q" type="search" placeholder="Cari kode / sekolah / ID sekolah…"
                   class="h-9 w-full rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        </div>
        <select wire:model.live="grup" class="{{ $ctrl }}">
            <option value="">Semua kategori</option>
            @foreach ($grupOptions as $val => $label)<option value="{{ $val }}" @selected($grup === (string) $val)>{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="statusBayar" class="{{ $ctrl }}">
            <option value="">Semua status</option>
            <option value="baru" @selected($statusBayar === 'baru')>Menunggu DP</option>
            <option value="dp" @selected($statusBayar === 'dp')>DP</option>
            <option value="lunas" @selected($statusBayar === 'lunas')>Lunas</option>
        </select>
        @if ($this->isLintasCabang)
            <select wire:model.live="cabangId" class="{{ $ctrl }}">
                <option value="">Semua cabang</option>
                @foreach ($cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($cabangId === (string) $id)>{{ $nama }}</option>@endforeach
            </select>
        @endif
        <div class="flex h-9 items-center gap-1.5 rounded-lg border border-line bg-card px-2.5">
            <span class="text-xs text-ink-muted">Booking</span>
            <input wire:model.live="dari" type="date" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>
        @if ($this->adaFilter())
            <button wire:click="resetFilter" class="h-9 rounded-lg border border-line bg-card px-3 text-sm text-ink-muted hover:text-ink">Reset</button>
        @endif
    </div>

    {{-- Tabel --}}
    <x-table min-width="1100px">
        <x-slot:head>
            <x-table.th sortable field="booking" :sort="$sortField" :dir="$sortDir">Booking</x-table.th>
            <x-table.th sortable field="tanggal" :sort="$sortField" :dir="$sortDir">Tgl order</x-table.th>
            <x-table.th>ID sekolah</x-table.th>
            <x-table.th>Marketing</x-table.th>
            <x-table.th>Sekolah</x-table.th>
            <x-table.th>Kategori</x-table.th>
            <x-table.th sortable field="total" :sort="$sortField" :dir="$sortDir" align="right">Subtotal</x-table.th>
            <x-table.th align="right">Diskon</x-table.th>
            <x-table.th align="right">Total</x-table.th>
            <x-table.th align="right">Dibayar</x-table.th>
            <x-table.th align="right">Outstanding</x-table.th>
        </x-slot:head>

        @forelse ($orders as $order)
            <x-table.tr>
                <x-table.td nowrap><span class="font-medium text-ink">{{ $order->booking_code ?? '#'.$order->id }}</span></x-table.td>
                <x-table.td nowrap muted>{{ optional($order->tanggal_booking)->translatedFormat('d M Y') }}</x-table.td>
                <x-table.td nowrap><span class="rounded-md bg-ink/5 px-1.5 py-0.5 text-xs text-ink-muted">{{ $order->sekolah?->id_sekolah ?? '—' }}</span></x-table.td>
                <x-table.td muted>{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</x-table.td>
                <x-table.td>{{ $order->sekolah?->nama ?? '—' }}</x-table.td>
                <x-table.td>
                    <button type="button" wire:click="lihatDetail({{ $order->id }})" class="flex flex-wrap items-center gap-1 hover:opacity-80" title="Lihat produk">
                        @foreach ($order->grupKategori() as $g)
                            <x-badge variant="navy">{{ \App\Models\Kategori::grupLabel($g) }}</x-badge>
                        @endforeach
                    </button>
                </x-table.td>
                <x-table.td nowrap align="right" muted>Rp{{ number_format($order->total, 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" muted>Rp{{ number_format($order->totalDiskon(), 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right">Rp{{ number_format($order->totalSetelahDiskon(), 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" class="text-status-success">Rp{{ number_format($order->totalDibayar(), 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" class="font-semibold text-brand-hover">Rp{{ number_format($order->outstanding(), 0, ',', '.') }}</x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="11">Tidak ada data untuk filter ini.</x-table.empty>
        @endforelse
    </x-table>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm text-ink-muted">
            <span>Per halaman</span>
            <select wire:model.live="perPage" class="{{ $ctrl }} h-8">@foreach ([25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach</select>
        </div>
        <div>{{ $orders->links() }}</div>
    </div>

    {{-- Modal detail produk + finansial --}}
    @if ($this->detailOrder)
        @php $d = $this->detailOrder; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/50 p-0 sm:items-center sm:p-4"
             x-data x-on:keydown.escape.window="$wire.tutupDetail()">
            <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-card shadow-2xl sm:rounded-2xl" x-on:click.outside="$wire.tutupDetail()">
                <div class="flex items-start justify-between gap-3 bg-gradient-to-br from-navy to-navy-hover px-5 py-4 text-white">
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-white/60">Detail order</div>
                        <div class="mt-0.5 truncate text-lg font-extrabold">{{ $d->booking_code ?? 'Order #'.$d->id }}</div>
                        <div class="mt-0.5 truncate text-xs text-white/70">{{ $d->sekolah?->nama }}</div>
                    </div>
                    <button type="button" wire:click="tutupDetail" class="rounded-lg p-1 text-white/70 hover:bg-white/10 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-5">
                    <h3 class="mb-2 text-sm font-medium text-ink">Produk dipesan</h3>
                    <div class="divide-y divide-line rounded-lg border border-line">
                        @foreach ($d->items as $item)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <span class="text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                                    @if ($item->is_free)<x-badge variant="success" class="ml-1">Free</x-badge>@endif
                                    <div class="text-xs text-ink-muted">
                                        @if ($item->desain) {{ $item->desain->kode }} · @endif
                                        @if ($item->opsi_ukuran) {{ $item->opsi_ukuran }} · @endif
                                        Rp{{ number_format($item->harga, 0, ',', '.') }} × {{ $item->qty }}
                                    </div>
                                </div>
                                <span class="shrink-0 font-medium text-ink">Rp{{ number_format($item->harga * $item->qty, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-muted">Subtotal</dt><dd class="text-ink">Rp{{ number_format($d->total, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Diskon</dt><dd class="text-ink">Rp{{ number_format($d->totalDiskon(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-medium text-ink">Total setelah diskon</dt><dd class="font-medium text-ink">Rp{{ number_format($d->totalSetelahDiskon(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Dibayar (DP + pelunasan)</dt><dd class="text-status-success">Rp{{ number_format($d->totalDibayar(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-bold text-ink">Outstanding</dt><dd class="font-bold text-brand-hover">Rp{{ number_format($d->outstanding(), 0, ',', '.') }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    @endif
</div>
