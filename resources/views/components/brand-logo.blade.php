@props([
    'size' => 'md',       // sm | md | lg
    'wordmark' => true,   // tampilkan teks nama di samping mark
    'tone' => 'dark',     // dark (teks gelap, bg terang) | light (teks putih, bg gelap)
])

@php
    $markH = [
        'sm' => 'h-6',
        'md' => 'h-7',
        'lg' => 'h-9',
    ][$size] ?? 'h-7';

    $titleTone = $tone === 'light' ? 'text-white' : 'text-ink';
    $subTone = $tone === 'light' ? 'text-white/60' : 'text-ink-muted';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    {{-- Logo DMA (mark) — latar transparan; di bg gelap dibungkus chip putih agar kontras. --}}
    @if ($tone === 'light')
        <span class="inline-flex items-center rounded-lg bg-white px-2 py-1.5">
            <img src="{{ asset('images/dma-mark.png') }}" alt="DMA" class="{{ $markH }} w-auto">
        </span>
    @else
        <img src="{{ asset('images/dma-mark.png') }}" alt="DMA" class="{{ $markH }} w-auto">
    @endif

    @if ($wordmark)
        <span class="flex flex-col leading-none">
            <span class="text-sm font-medium tracking-tight {{ $titleTone }}">Delapan Mata Air</span>
            <span class="text-[10px] {{ $subTone }}">Studio Foto</span>
        </span>
    @endif
</span>
