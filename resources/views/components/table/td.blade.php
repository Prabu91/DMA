@props([
    'align' => 'left',   // left | right | center
    'nowrap' => false,
    'muted' => false,
])

@php
    $alignClass = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'][$align] ?? 'text-left';
@endphp

<td {{ $attributes->merge(['class' => 'px-4 py-3 align-middle '.$alignClass.($nowrap ? ' whitespace-nowrap' : '').($muted ? ' text-ink-muted' : ' text-ink')]) }}>
    {{ $slot }}
</td>
