{{-- Kartu daftar order klik-able → detail order. Variabel: $orders, $title?, $emptyText? --}}
<x-card :title="$title ?? 'Order terbaru'" padding="p-0">
    @if (($showAll ?? false) && $orders->isNotEmpty())
        <x-slot name="actions">
            <a href="{{ route('app.order.index') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-hover">Lihat semua →</a>
        </x-slot>
    @endif

    @forelse ($orders as $order)
        <a href="{{ route('app.order.show', $order->id) }}" wire:navigate
           class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-page">
            <div class="min-w-0">
                <div class="truncate text-sm text-ink">{{ $order->sekolah?->nama ?? 'Tanpa sekolah' }}</div>
                <div class="truncate text-xs text-ink-muted">
                    {{ $order->booking_code ?? 'Order #'.$order->id }}
                    @if ($order->tanggal_event) · {{ $order->tanggal_event->translatedFormat('d M Y') }} @endif
                    @isset($order->cabang) · {{ $order->cabang?->nama }} @endisset
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @php $cd = $order->eventCountdown(); @endphp
                @if ($cd && $order->status !== 'batal')
                    <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                @endif
                <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">{{ \App\Support\OrderStatus::label($order->status) }}</x-badge>
                <svg class="h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </div>
        </a>
    @empty
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-ink-muted">{{ $emptyText ?? 'Belum ada order.' }}</p>
        </div>
    @endforelse
</x-card>
