@props([
    'size' => 'md',       // sm | md | lg
    'wordmark' => true,   // tampilkan teks "DMA" di samping mark
    'tone' => 'dark',     // dark (teks gelap) | light (teks putih, untuk bg gelap)
])

@php
    $markSizes = [
        'sm' => 'h-8 w-8',
        'md' => 'h-10 w-10',
        'lg' => 'h-12 w-12',
    ];
    $mark = $markSizes[$size] ?? $markSizes['md'];

    $titleTone = $tone === 'light' ? 'text-white' : 'text-ink';
    $subTone = $tone === 'light' ? 'text-white/60' : 'text-ink-muted';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    {{--
        PLACEHOLDER LOGO — ganti seluruh blok <svg> ini dengan file logo HD DMA
        saat tersedia (mis. <img src="{{ asset('images/logo.svg') }}" class="{{ $mark }}">).
        Mark: kotak membulat brand + glyph aperture.
    --}}
    <svg class="{{ $mark }}" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="48" height="48" rx="12" class="fill-brand" />
        <circle cx="24" cy="24" r="11" fill="none" stroke="#fff" stroke-width="2.5" />
        <path d="M24 13 L24 24 L32 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>

    @if ($wordmark)
        <span class="flex flex-col leading-none">
            <span class="text-base font-medium tracking-tight {{ $titleTone }}">DMA</span>
            <span class="text-[10px] {{ $subTone }}">Studio foto</span>
        </span>
    @endif
</span>
