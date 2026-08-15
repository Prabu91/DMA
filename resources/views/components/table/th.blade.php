@props([
    'align' => 'left',    // left | right | center
    'sortable' => false,
    'field' => null,      // nama field sort (harus di-whitelist komponen Livewire)
    'sort' => null,       // field sort aktif
    'dir' => 'asc',       // arah sort aktif
])

@php
    $alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
    $active = $sortable && $field && $sort === $field;
@endphp

<th {{ $attributes->merge(['class' => "px-4 py-3 font-medium whitespace-nowrap $alignClass"]) }}>
    @if ($sortable && $field)
        <button type="button" x-on:click="$wire.sortBy('{{ $field }}')"
                class="group inline-flex items-center gap-1 uppercase tracking-wide hover:text-ink {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
            <span>{{ $slot }}</span>
            <span @class(['text-brand' => $active, 'text-ink-muted/40 group-hover:text-ink-muted' => ! $active])>
                @if ($active && $dir === 'asc')
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 3l3 4H3z" /></svg>
                @elseif ($active && $dir === 'desc')
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 9L3 5h6z" /></svg>
                @else
                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor"><path d="M6 2l2.5 3h-5zM6 10L3.5 7h5z" /></svg>
                @endif
            </span>
        </button>
    @else
        {{ $slot }}
    @endif
</th>
