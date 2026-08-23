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
    </div>

    {{-- Mobile: kartu ringkas (tabel disembunyikan) --}}
    <div class="space-y-2 md:hidden">
        @forelse ($orders as $order)
            @php $cd = $order->eventCountdown(); @endphp
            <a href="{{ route('app.order.show', $order->id) }}" wire:navigate class="block rounded-xl border border-line bg-card p-3.5 transition-colors hover:border-brand/40">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-brand">{{ $order->booking_code ?? 'Order #'.$order->id }}</span>
                    <span class="shrink-0 font-medium text-ink">Rp{{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ \App\Support\OrderStatus::label($order->status) }}</x-badge>
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
                    <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ \App\Support\OrderStatus::label($order->status) }}</x-badge>
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

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>
