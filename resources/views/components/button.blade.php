@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 disabled:opacity-50 disabled:pointer-events-none';

    $sizes = [
        'sm' => 'min-h-[36px] px-3 text-sm',
        'md' => 'min-h-[44px] px-4 text-sm',   // target sentuh 44px
        'lg' => 'min-h-[48px] px-5 text-base',
    ];

    $variants = [
        'primary' => 'bg-brand text-white hover:bg-brand-hover',
        'secondary' => 'bg-card text-ink border border-line hover:bg-page',
        'ghost' => 'text-ink hover:bg-page',
        'danger' => 'bg-status-danger text-white hover:opacity-90',
    ];

    $classes = $base
        .' '.($sizes[$size] ?? $sizes['md'])
        .' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
