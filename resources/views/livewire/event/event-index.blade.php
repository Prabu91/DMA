<div>
    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Jadwal Event</h1>
        <p class="text-sm text-ink-muted">Event yang ditugaskan kepada Anda — buka detail untuk konfirmasi & penyelesaian.</p>
    </div>

    {{-- Filter status --}}
    <div class="mb-4 w-full sm:w-52">
        <x-select wire:model.live="status" :selected="$status" :options="$statusOptions" />
    </div>

    <x-card padding="p-0">
        @forelse ($events as $order)
            <a href="{{ route('app.event.show', $order->id) }}" wire:navigate
               class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0 hover:bg-page">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($order->booking_code)
                            <span class="text-sm font-medium tracking-wide text-ink">{{ $order->booking_code }}</span>
                        @else
                            <span class="text-sm text-ink">Event #{{ $order->id }}</span>
                        @endif
                        <x-badge :variant="\App\Support\OrderStatus::badge($order->event_status)">{{ \App\Support\OrderStatus::label($order->event_status) }}</x-badge>
                        @php $cd = $order->eventCountdown(); @endphp
                        @if ($cd)
                            <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                        @endif
                    </div>
                    <div class="mt-0.5 truncate text-xs text-ink-muted">
                        {{ $order->sekolah?->nama }} · {{ $order->cabang?->nama }}
                        · {{ $order->tanggal_event->translatedFormat('d M Y') }}
                        @if ($order->jam_event) · {{ $order->jam_event }} @endif
                    </div>
                </div>
                <svg class="h-5 w-5 shrink-0 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
        @empty
            <div class="px-5 py-12 text-center">
                <p class="text-sm text-ink-muted">Belum ada event yang cocok.</p>
                <p class="mt-1 text-xs text-ink-muted">Event yang di-assign ke Anda akan muncul di sini.</p>
            </div>
        @endforelse
    </x-card>

    <div class="mt-4">
        {{ $events->links() }}
    </div>
</div>
