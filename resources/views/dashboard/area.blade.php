<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard area</h1>
        <p class="text-sm text-ink-muted">Ringkasan operasional cabang Anda.</p>
    </x-slot>

    <div class="space-y-6">
        @include('dashboard.partials.stats', ['stats' => $data['stats']])

        <x-card title="Order terbaru" padding="p-0">
            @forelse ($data['recentOrders'] as $order)
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <div class="truncate text-sm text-ink">{{ $order->sekolah?->nama ?? 'Tanpa sekolah' }}</div>
                        <div class="truncate text-xs text-ink-muted">
                            {{ $order->booking_code }}
                            @if ($order->tanggal_event)
                                · {{ $order->tanggal_event->translatedFormat('d M Y') }}
                            @endif
                        </div>
                    </div>
                    <x-badge :variant="\App\Support\OrderStatus::badge($order->status)">
                        {{ \App\Support\OrderStatus::label($order->status) }}
                    </x-badge>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm text-ink-muted">Belum ada order di cabang ini.</p>
                </div>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
