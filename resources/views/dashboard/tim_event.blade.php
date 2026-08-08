<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard tim event</h1>
        <p class="text-sm text-ink-muted">Event yang ditugaskan kepada Anda.</p>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'event-selesai')
            <div class="rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">
                Event ditandai selesai.
            </div>
        @endif

        @include('dashboard.partials.stats', ['stats' => $data['stats']])

        <x-card title="Event ditugaskan" padding="p-0">
            <x-slot name="actions">
                <a href="{{ route('app.event.index') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-hover">Lihat semua →</a>
            </x-slot>
            @forelse ($data['assignedEvents'] as $order)
                <a href="{{ route('app.event.show', $order->id) }}" wire:navigate
                   class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-page">
                    <div class="min-w-0">
                        <div class="truncate text-sm text-ink">{{ $order->sekolah?->nama ?? 'Tanpa sekolah' }}</div>
                        <div class="truncate text-xs text-ink-muted">
                            {{ $order->booking_code }}
                            @if ($order->tanggal_event)
                                · {{ $order->tanggal_event->translatedFormat('d M Y') }}
                            @endif
                            @if ($order->jam_event)
                                · {{ $order->jam_event }}
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-badge :variant="\App\Support\OrderStatus::badge($order->event_status)">
                            {{ \App\Support\OrderStatus::label($order->event_status) }}
                        </x-badge>
                        @php $cd = $order->eventCountdown(); @endphp
                        @if ($cd && $order->event_status !== \App\Support\OrderStatus::EVENT_SELESAI)
                            <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                        @endif
                        <svg class="h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </div>
                </a>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm text-ink-muted">Belum ada event yang ditugaskan.</p>
                    <p class="mt-1 text-xs text-ink-muted">Event yang di-assign ke Anda akan muncul di sini.</p>
                </div>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
