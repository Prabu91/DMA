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
    // password tidak pernah dipulihkan dari old() demi keamanan.
    $resolved = $type === 'password' ? null : old($name, $value);
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if (! is_null($resolved)) value="{{ $resolved }}" @endif
        {{ $attributes->merge([
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
