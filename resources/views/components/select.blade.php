@props([
    'name' => null,
    'label' => null,
    'options' => [],       // assoc: [value => label]
    'selected' => null,
    'placeholder' => null, // opsi kosong di atas (value "")
    'hint' => null,
    'error' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $err = $error ?? ($name ? $errors->first($name) : null);
    $current = $name ? old($name, $selected) : $selected;
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <select
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->merge([
            'class' => 'block w-full min-h-[44px] rounded-lg border bg-card py-2 pl-3 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand '
                . ($err ? 'border-status-danger' : 'border-line'),
        ]) }}
    >
        @if (! is_null($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>

    @if ($err)
        <p class="text-xs text-status-danger">{{ $err }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
