@props([
    'name' => '',
    'size' => 'md',
])

@php
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? end($parts) : '';
    $initials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    if ($initials === '') {
        $initials = '?';
    }

    $sizes = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-14 w-14 text-lg',
    ];

    $classes = 'inline-flex shrink-0 items-center justify-center rounded-full bg-brand/15 font-medium text-brand '
        . ($sizes[$size] ?? $sizes['md']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }} aria-hidden="true">
    {{ $initials }}
</span>
