@props(['variant' => 'neutral'])

@php
    // Status pipeline: tinted bg + teks solid. Brand/navy tersedia untuk aksen non-status.
    $variants = [
        'success' => 'bg-status-success/10 text-status-success',
        'pending' => 'bg-status-pending/10 text-status-pending',
        'info' => 'bg-status-info/10 text-status-info',
        'danger' => 'bg-status-danger/10 text-status-danger',
        'brand' => 'bg-brand/10 text-brand',
        'navy' => 'bg-navy/10 text-navy',
        'neutral' => 'bg-ink/5 text-ink-muted',
    ];

    $classes = 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium '
        . ($variants[$variant] ?? $variants['neutral']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
