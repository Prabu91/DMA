@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-card']) }}>
    @if ($title || isset($actions))
        <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                @if ($title)
                    <h3 class="text-sm font-medium text-ink">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-ink-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>
