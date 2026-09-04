@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $err = $error ?? ($name ? $errors->first($name) : null);
    // password tidak pernah dipulihkan dari old(); old() hanya bila punya name
    // (tanpa name, old(null) mengembalikan seluruh array input → error).
    $resolved = $type === 'password'
        ? null
        : ($name ? old($name, $value) : $value);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if (! is_null($resolved)) value="{{ $resolved }}" @endif
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full min-h-[44px] rounded-lg border bg-card px-3 text-sm text-ink placeholder:text-ink-muted/60 focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand '
                . ($err ? 'border-status-danger' : 'border-line'),
        ]) }}
    />

    @if ($err)
        <p class="text-xs text-status-danger">{{ $err }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
