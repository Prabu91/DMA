@props([
    'label' => '',
    'value' => '—',
    'hint' => null,
    'icon' => null,       // path 'd' SVG opsional
    'accent' => 'brand',  // brand | navy | success | pending | info | danger
])

@php
    // Kelas literal (bukan interpolasi) supaya tidak ter-purge Tailwind.
    $accents = [
        'brand' => 'bg-brand/10 text-brand',
        'navy' => 'bg-navy/10 text-navy',
        'success' => 'bg-status-success/10 text-status-success',
        'pending' => 'bg-status-pending/10 text-status-pending',
        'info' => 'bg-status-info/10 text-status-info',
        'danger' => 'bg-status-danger/10 text-status-danger',
    ];
    $accentClasses = $accents[$accent] ?? $accents['brand'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-card p-4']) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="text-xs font-medium text-ink-muted">{{ $label }}</span>
        @if ($icon)
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $accentClasses }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>

    <div class="mt-2 text-2xl font-medium text-ink">{{ $value }}</div>

    @if ($hint)
        <div class="mt-1 text-xs text-ink-muted">{{ $hint }}</div>
    @endif
</div>
