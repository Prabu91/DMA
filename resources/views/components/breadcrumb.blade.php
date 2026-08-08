@props([
    'items' => [], // array of ['label' => string, 'url' => ?string]
])

<nav class="mb-4 flex flex-wrap items-center gap-1.5 text-sm" aria-label="Breadcrumb">
    @foreach ($items as $item)
        @if (! $loop->last && ! empty($item['url']))
            <a href="{{ $item['url'] }}" wire:navigate class="text-ink-muted transition-colors hover:text-ink">{{ $item['label'] }}</a>
        @else
            <span @class(['font-medium text-ink' => $loop->last, 'text-ink-muted' => ! $loop->last])>{{ $item['label'] }}</span>
        @endif

        @unless ($loop->last)
            <svg class="h-3.5 w-3.5 text-ink-muted/50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        @endunless
    @endforeach
</nav>
