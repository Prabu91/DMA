{{--
    Status QC satu item order (panel staf). Tim event: hanya dibaca di sini
    (dicentang di halaman event). Admin: bisa dicentang sesudah Hari-H.
--}}
@php
    $cekEvent = $item->sudahQc('event');
    $cekAdmin = $item->sudahQc('admin');
    $namaEvent = $item->qcEventOleh?->nama ?? $item->qcEventOleh?->name;
    $namaAdmin = $item->qcAdminOleh?->nama ?? $item->qcAdminOleh?->name;
@endphp
<div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
    <span @class([
        'inline-flex items-center gap-1 rounded-md px-2 py-1',
        'bg-status-success/10 text-status-success' => $cekEvent,
        'bg-ink/5 text-ink-muted' => ! $cekEvent,
    ]) title="{{ $cekEvent ? 'Dicek '.$namaEvent.' · '.$item->qc_event_at->translatedFormat('d M H:i') : 'Belum dicek tim event' }}">
        {{ $cekEvent ? '✓' : '○' }} Tim event
    </span>

    @if ($this->bisaQcAdmin)
        <button type="button" wire:click="toggleQcAdmin({{ $item->id }})"
                wire:loading.attr="disabled" wire:target="toggleQcAdmin({{ $item->id }})"
                aria-pressed="{{ $cekAdmin ? 'true' : 'false' }}"
                @class([
                    'inline-flex min-h-[32px] items-center gap-1.5 rounded-md border px-2 py-1 font-medium transition-colors disabled:opacity-60',
                    'border-status-success/40 bg-status-success/10 text-status-success' => $cekAdmin,
                    'border-line bg-card text-ink hover:border-brand/40' => ! $cekAdmin,
                ])>
            <span @class([
                'flex h-4 w-4 items-center justify-center rounded border',
                'border-status-success bg-status-success text-white' => $cekAdmin,
                'border-line' => ! $cekAdmin,
            ])>@if ($cekAdmin)<svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>@endif</span>
            Admin
        </button>
    @else
        <span @class([
            'inline-flex items-center gap-1 rounded-md px-2 py-1',
            'bg-status-success/10 text-status-success' => $cekAdmin,
            'bg-ink/5 text-ink-muted' => ! $cekAdmin,
        ])>{{ $cekAdmin ? '✓' : '○' }} Admin</span>
    @endif

    @if ($cekAdmin && $namaAdmin)
        <span class="text-ink-muted">{{ $namaAdmin }} · {{ $item->qc_admin_at->translatedFormat('d M H:i') }}</span>
    @elseif ($cekEvent && $namaEvent)
        <span class="text-ink-muted">dicek {{ $namaEvent }} · {{ $item->qc_event_at->translatedFormat('d M H:i') }}</span>
    @endif
</div>
