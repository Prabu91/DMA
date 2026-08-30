@props([
    'action',                    // nama method Livewire, mis. "approvePembayaran"
    'arg' => null,               // argumen opsional (id / key)
    'message' => 'Lanjutkan tindakan ini?',
    'title' => 'Konfirmasi',
    'confirmLabel' => 'Ya, lanjut',
    'variant' => 'primary',      // gaya tombol pemicu
    'confirmVariant' => null,    // gaya tombol konfirmasi (default = variant)
    'size' => 'md',
    'disabled' => false,
    'block' => false,            // tombol pemicu selebar kontainer
    'triggerClass' => '',
])

@php
    $call = "\$wire.call('{$action}'" . ($arg !== null ? ', ' . \Illuminate\Support\Js::from($arg) : '') . ')';
    $confirmVariant = $confirmVariant ?? ($variant === 'ghost' ? 'primary' : $variant);
@endphp

<div x-data="{ open: false }" class="{{ $block ? 'block' : 'inline-block' }}">
    @isset($trigger)
        {{ $trigger }}
    @else
        <x-button type="button" :variant="$variant" :size="$size" :disabled="$disabled"
                  class="{{ $block ? 'w-full' : '' }} {{ $triggerClass }}"
                  x-on:click="{{ $disabled ? '' : 'open = true' }}">{{ $slot }}</x-button>
    @endisset

    {{-- Tanpa x-teleport agar $wire tetap dalam scope komponen Livewire. --}}
    <div x-show="open" x-cloak
         x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-[70] flex items-center justify-center p-4 text-left" style="display:none">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-900/70 backdrop-blur-sm" x-on:click="open = false"></div>

        <div x-show="open" x-transition
             class="relative z-10 w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="px-5 pt-5">
                <h3 class="text-base font-bold text-ink">{{ $title }}</h3>
                <p class="mt-1.5 text-sm text-ink-muted">{{ $message }}</p>
            </div>
            <div class="mt-5 flex justify-end gap-2 border-t border-line bg-page px-5 py-3">
                <x-button type="button" variant="ghost" size="sm" x-on:click="open = false">Batal</x-button>
                <x-button type="button" :variant="$confirmVariant" size="sm"
                          x-on:click="open = false; {{ $call }}">{{ $confirmLabel }}</x-button>
            </div>
        </div>
    </div>
</div>
