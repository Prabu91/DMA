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
            @forelse ($data['assignedEvents'] as $order)
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
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

                        @if ($order->event_status !== \App\Support\OrderStatus::EVENT_SELESAI)
                            <form method="POST" action="{{ route('events.complete', $order) }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">Tandai selesai</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm text-ink-muted">Belum ada event yang ditugaskan.</p>
                    <p class="mt-1 text-xs text-ink-muted">Event yang di-assign ke Anda akan muncul di sini.</p>
                </div>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
