{{-- Riwayat aktivitas + pihak terlibat. Vars: $order, $activities (collection OrderActivity dg user). --}}
<x-card title="Riwayat & pihak terlibat">
    {{-- Pihak terlibat --}}
    <div class="mb-4 flex flex-wrap gap-1.5">
        @if ($order->marketing)
            <span class="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2.5 py-1 text-xs font-medium text-brand">
                Marketing: {{ $order->marketing->nama ?? $order->marketing->name }}
            </span>
        @endif
        @foreach ($order->timEvent as $t)
            <span class="inline-flex items-center gap-1 rounded-full bg-navy/10 px-2.5 py-1 text-xs font-medium text-navy">
                Tim: {{ $t->nama ?? $t->name }}
            </span>
        @endforeach
        @if (! $order->marketing && $order->timEvent->isEmpty())
            <span class="text-xs text-ink-muted">Belum ada penugasan.</span>
        @endif
    </div>

    {{-- Timeline --}}
    <ol class="relative ml-1 space-y-4 border-l border-line pl-4">
        @forelse ($activities as $a)
            <li class="relative">
                <span @class([
                    'absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full ring-4 ring-card',
                    'bg-status-danger' => $a->tone() === 'danger',
                    'bg-status-success' => $a->tone() === 'success',
                    'bg-status-info' => $a->tone() === 'info',
                    'bg-ink-muted/40' => $a->tone() === 'neutral',
                ])></span>
                <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                    <span class="text-sm font-medium text-ink">{{ $a->label() }}</span>
                    <span class="text-xs text-ink-muted">{{ $a->created_at?->translatedFormat('d M Y · H:i') }}</span>
                </div>
                <div class="text-xs text-ink-muted">
                    {{ $a->user?->nama ?? $a->user?->name ?? 'Sistem/Sekolah' }}@if ($a->description) · {{ $a->description }}@endif
                </div>
            </li>
        @empty
            <li class="text-sm text-ink-muted">Belum ada aktivitas.</li>
        @endforelse
    </ol>
</x-card>
