<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Order'],
    ]" />

    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Order</h1>
        <p class="text-sm text-ink-muted">Semua pesanan di cabang Anda — pantau status & jadwal.</p>
    </div>

    {{-- Strip per-cabang (admin) --}}
    @if ($isAdmin && $cabangs->isNotEmpty())
        @include('booking.partials.cabang-strip', ['cabangs' => $cabangs, 'counts' => $cabangCounts, 'aktif' => $cabangId, 'total' => $cabangTotal])
    @endif

    {{-- Filter (ringkas, satu baris membungkus) --}}
    @php $ctrl = 'h-9 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[180px] flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="q" type="search" placeholder="Cari kode / sekolah…"
                   class="h-9 w-full rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        </div>

        <select wire:model.live="status" class="{{ $ctrl }}">
            @foreach ($statusOptions as $v => $t)<option value="{{ $v }}" @selected($status === (string) $v)>{{ $t }}</option>@endforeach
        </select>
        <select wire:model.live="eventStatus" class="{{ $ctrl }}">
            @foreach ($eventStatusOptions as $v => $t)<option value="{{ $v }}" @selected($eventStatus === (string) $v)>{{ $t }}</option>@endforeach
        </select>
        <select wire:model.live="tahap" class="{{ $ctrl }}">
            @foreach ($tahapOptions as $v => $t)<option value="{{ $v }}" @selected($tahap === (string) $v)>{{ $t }}</option>@endforeach
        </select>

        <div class="flex items-center gap-1.5 rounded-lg border border-line bg-card px-2.5 h-9">
            <span class="text-xs text-ink-muted">Event</span>
            <input wire:model.live="dari" type="date" title="Event dari" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" title="Event sampai" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>

        @if ($q !== '' || $status !== '' || $eventStatus !== '' || $tahap !== '' || $dari !== '' || $sampai !== '' || $cabangId !== '')
            <button type="button" wire:click="resetFilter" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand hover:bg-brand/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                Reset
            </button>
        @endif

        @if ($this->isSuperAdmin)
            <button type="button" wire:click="$toggle('sampah')"
                    @class([
                        'ml-auto inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-medium transition-colors',
                        'bg-status-danger/10 text-status-danger' => $sampah,
                        'text-ink-muted hover:bg-page' => ! $sampah,
                    ])>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                {{ $sampah ? 'Keluar dari sampah' : 'Terhapus' }}
            </button>
        @endif
    </div>

    @if ($sampah)
        <div class="mb-3 rounded-lg border border-status-danger/20 bg-status-danger/5 px-4 py-2.5 text-sm text-ink">
            Menampilkan <span class="font-medium">order terhapus</span>. Bisa dipulihkan hingga {{ \App\Models\Order::TRASH_RETENTION_DAYS }} hari sejak dihapus.
        </div>
    @endif

    {{-- Sampah: daftar order terhapus + pulihkan/hapus permanen (super admin) --}}
    @if ($sampah)
        <div class="space-y-2">
            @forelse ($orders as $order)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-card p-3.5">
                    <div class="min-w-0">
                        <div class="font-medium text-ink">{{ $order->booking_code ?? 'Order #'.$order->id }}</div>
                        <div class="text-xs text-ink-muted">
                            {{ $order->sekolah?->nama ?? '—' }} · {{ $order->cabang?->nama }} · Rp{{ number_format($order->total, 0, ',', '.') }}
                            @if ($order->deleted_at) · dihapus {{ $order->deleted_at->translatedFormat('d M Y H:i') }}@endif
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <x-confirm action="pulihkan" :arg="$order->id" title="Pulihkan order" message="Pulihkan order {{ $order->booking_code ?? '#'.$order->id }} dari sampah?" confirm-label="Pulihkan" variant="secondary" size="sm">Pulihkan</x-confirm>
                        <x-confirm action="hapusPermanen" :arg="$order->id" title="Hapus permanen" message="Hapus PERMANEN order ini beserta seluruh datanya (item, pembayaran, aktivitas)? Tidak bisa dipulihkan." confirm-label="Ya, hapus permanen" variant="ghost" confirm-variant="danger" size="sm">Hapus permanen</x-confirm>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-line bg-card px-4 py-10 text-center text-sm text-ink-muted">Sampah kosong.</div>
            @endforelse
        </div>
    @endif

    {{-- Mobile: kartu ringkas (tabel disembunyikan) --}}
    @unless ($sampah)
    <div class="space-y-2 md:hidden">
        @forelse ($orders as $order)
            @php $cd = $order->eventCountdown(); @endphp
            <a href="{{ route('app.order.show', $order->id) }}" wire:navigate class="block rounded-xl border border-line bg-card p-3.5 transition-colors hover:border-brand/40">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-brand">{{ $order->booking_code ?? 'Order #'.$order->id }}</span>
                    <span class="shrink-0 font-medium text-ink">Rp{{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ $order->statusLabel() }}</x-badge>
                    @if ($order->event_status === \App\Support\OrderStatus::EVENT_SELESAI)<x-badge variant="success">✓ Event selesai</x-badge>@endif
                    @unless ($order->marketing)<x-badge variant="neutral">Belum ditugaskan</x-badge>@endunless
                    @if ($cd)<x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>@endif
                </div>
                <div class="mt-1.5 text-sm text-ink">{{ $order->sekolah?->nama ?? '—' }}</div>
                <div class="mt-0.5 text-xs text-ink-muted">
                    {{ $order->cabang?->nama }} · {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa
                    · Event {{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('d M Y') : '—' }}
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-line bg-card px-4 py-10 text-center text-sm text-ink-muted">Belum ada order yang cocok.</div>
        @endforelse
    </div>

    {{-- Desktop: tabel penuh --}}
    <div class="hidden md:block">
    <x-table min-width="900px">
        <x-slot:head>
            <x-table.th sortable field="booking" :sort="$sortField" :dir="$sortDir">Order</x-table.th>
            <x-table.th sortable field="status" :sort="$sortField" :dir="$sortDir">Status</x-table.th>
            <x-table.th>Sekolah</x-table.th>
            <x-table.th sortable field="event" :sort="$sortField" :dir="$sortDir">Event</x-table.th>
            <x-table.th>Marketing</x-table.th>
            <x-table.th sortable field="total" :sort="$sortField" :dir="$sortDir" align="right">Total</x-table.th>
        </x-slot:head>

        @forelse ($orders as $order)
            <x-table.tr>
                <x-table.td nowrap>
                    <a href="{{ route('app.order.show', $order->id) }}" wire:navigate class="font-medium text-brand hover:text-brand-hover">
                        {{ $order->booking_code ?? 'Order #'.$order->id }}
                    </a>
                    @php $cd = $order->eventCountdown(); @endphp
                    @if ($cd)<x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')" class="ml-1">{{ $cd['label'] }}</x-badge>@endif
                </x-table.td>
                <x-table.td>
                    <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ $order->statusLabel() }}</x-badge>
                    @if ($order->event_status === \App\Support\OrderStatus::EVENT_SELESAI)<x-badge variant="success" class="ml-1">✓ Event selesai</x-badge>@endif
                    @unless ($order->marketing)<x-badge variant="neutral" class="ml-1">Belum ditugaskan</x-badge>@endunless
                </x-table.td>
                <x-table.td>
                    <div class="text-ink">{{ $order->sekolah?->nama ?? '—' }}</div>
                    <div class="text-xs text-ink-muted">{{ $order->cabang?->nama }} · {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa</div>
                </x-table.td>
                <x-table.td nowrap muted>{{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('d M Y') : '—' }}</x-table.td>
                <x-table.td muted>{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</x-table.td>
                <x-table.td align="right" nowrap class="font-medium">Rp{{ number_format($order->total, 0, ',', '.') }}</x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="6">Belum ada order yang cocok.</x-table.empty>
        @endforelse
    </x-table>
    </div>
    @endunless

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>
