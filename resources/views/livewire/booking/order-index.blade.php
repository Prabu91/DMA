<div>
    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Order</h1>
        <p class="text-sm text-ink-muted">Semua pesanan di cabang Anda — pantau status & jadwal.</p>
    </div>

    {{-- Strip per-cabang (admin) --}}
    @if ($isAdmin && $cabangs->isNotEmpty())
        @include('booking.partials.cabang-strip', ['cabangs' => $cabangs, 'counts' => $cabangCounts, 'aktif' => $cabangId, 'total' => $cabangTotal])
    @endif

    {{-- Filter (ringkas, satu baris membungkus) --}}
    @php $ctrl = 'h-9 rounded-lg border border-line bg-card px-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
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

    <x-card padding="p-0">
        @forelse ($orders as $order)
            <a href="{{ route('app.order.show', $order->id) }}" wire:navigate
               class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0 hover:bg-page">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($order->booking_code)
                            <span class="text-sm font-medium tracking-wide text-ink">{{ $order->booking_code }}</span>
                        @else
                            <span class="text-sm text-ink">Order #{{ $order->id }}</span>
                        @endif
                        <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ \App\Support\OrderStatus::label($order->status) }}</x-badge>
                        @unless ($order->marketing)<x-badge variant="neutral">Belum ditugaskan</x-badge>@endunless
                        @php $cd = $order->eventCountdown(); @endphp
                        @if ($cd)
                            <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                        @endif
                    </div>
                    <div class="mt-0.5 truncate text-xs text-ink-muted">
                        {{ $order->sekolah?->nama }} · {{ $order->cabang?->nama }}
                        · Event: {{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('d M Y') : '—' }}
                        · {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa
                        @if ($order->marketing) · {{ $order->marketing->nama ?? $order->marketing->name }} @endif
                    </div>
                </div>
                <div class="shrink-0 text-right text-sm font-medium text-ink">Rp{{ number_format($order->total, 0, ',', '.') }}</div>
            </a>
        @empty
            <div class="px-5 py-12 text-center text-sm text-ink-muted">Belum ada order yang cocok.</div>
        @endforelse
    </x-card>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>
