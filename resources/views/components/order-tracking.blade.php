@props(['order'])

@php
    $batal = $order->status === \App\Support\OrderStatus::BATAL;

    // Tahapan tracking (urut). done = tercapai; at = waktu (opsional).
    $steps = [
        ['label' => 'Pesanan dibuat', 'done' => true, 'at' => $order->tanggal_booking],
        ['label' => 'Marketing ditugaskan', 'done' => $order->marketing_id !== null, 'at' => null],
        ['label' => 'DP dibayar', 'done' => in_array($order->status, [\App\Support\OrderStatus::DP, \App\Support\OrderStatus::LUNAS], true), 'at' => null],
        ['label' => 'Konfirmasi H-7', 'done' => $order->konfirmasi_h7_at !== null, 'at' => $order->konfirmasi_h7_at],
        ['label' => 'Konfirmasi H-2 · STE terbit', 'done' => $order->konfirmasi_h2_at !== null, 'at' => $order->konfirmasi_h2_at],
        ['label' => 'Hari-H (final)', 'done' => $order->konfirmasi_hh_at !== null, 'at' => $order->konfirmasi_hh_at],
        ['label' => 'Event selesai', 'done' => $order->event_status === \App\Support\OrderStatus::EVENT_SELESAI, 'at' => $order->event_selesai_at],
        ['label' => 'Tim sampai kantor', 'done' => $order->sampai_kantor_at !== null, 'at' => $order->sampai_kantor_at],
    ];

    // Tahap "berjalan" = tahap done pertama yang belum diikuti tahap berikutnya.
    $currentIndex = null;
    foreach ($steps as $i => $s) {
        if (! $s['done']) { $currentIndex = $i; break; }
    }
@endphp

<div {{ $attributes }}>
    @if ($batal)
        <div class="rounded-lg border-l-4 border-status-danger bg-status-danger/10 px-4 py-3 text-sm font-medium text-status-danger">
            Pesanan dibatalkan.
        </div>
    @else
        <ol class="relative space-y-0">
            @foreach ($steps as $i => $s)
                @php
                    $isCurrent = $i === $currentIndex;
                    $isDone = $s['done'];
                    $last = $i === count($steps) - 1;
                @endphp
                <li class="relative flex gap-3 pb-5 last:pb-0">
                    {{-- Garis penghubung --}}
                    @unless ($last)
                        <span class="absolute left-[11px] top-6 h-[calc(100%-1rem)] w-px {{ $isDone ? 'bg-brand' : 'bg-line' }}"></span>
                    @endunless

                    {{-- Titik --}}
                    <span @class([
                        'relative z-10 mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full border-2',
                        'border-brand bg-brand text-white' => $isDone,
                        'border-brand bg-card text-brand' => $isCurrent && ! $isDone,
                        'border-line bg-card text-ink-muted' => ! $isDone && ! $isCurrent,
                    ])>
                        @if ($isDone)
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        @else
                            <span class="h-1.5 w-1.5 rounded-full {{ $isCurrent ? 'bg-brand' : 'bg-line' }}"></span>
                        @endif
                    </span>

                    <div class="min-w-0 pt-0.5">
                        <div @class([
                            'text-sm',
                            'font-medium text-ink' => $isDone || $isCurrent,
                            'text-ink-muted' => ! $isDone && ! $isCurrent,
                        ])>{{ $s['label'] }}</div>
                        @if ($isDone && $s['at'])
                            <div class="text-xs text-ink-muted">{{ $s['at']->translatedFormat('d M Y · H:i') }}</div>
                        @elseif ($isCurrent)
                            <div class="text-xs text-brand">Sedang berjalan</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
