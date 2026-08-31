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
    $isDanger = $confirmVariant === 'danger';
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
         class="fixed inset-0 z-[70] flex items-end justify-center p-4 text-left sm:items-center" style="display:none">
        <div x-show="open" x-transition.opacity.duration.200ms
             class="absolute inset-0 bg-navy-900/60 backdrop-blur-sm" x-on:click="open = false"></div>

        <div x-show="open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative z-10 w-full max-w-sm overflow-hidden rounded-2xl bg-card shadow-2xl ring-1 ring-black/5">
            <div class="p-5">
                <div class="flex gap-4">
                    <span @class([
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
                        'bg-status-danger/10 text-status-danger' => $isDanger,
                        'bg-brand/10 text-brand' => ! $isDanger,
                    ])>
                        @if ($isDanger)
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        @else
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                        @endif
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <h3 class="text-base font-bold text-ink">{{ $title }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-ink-muted">{{ $message }}</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-line bg-page/60 px-5 py-3 sm:flex-row sm:justify-end">
                <x-button type="button" variant="secondary" size="sm" class="sm:w-auto w-full" x-on:click="open = false">Batal</x-button>
                <x-button type="button" :variant="$confirmVariant" size="sm" class="sm:w-auto w-full"
                          x-on:click="open = false; {{ $call }}">{{ $confirmLabel }}</x-button>
            </div>
        </div>
    </div>
</div>
