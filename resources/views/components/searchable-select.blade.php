@props([
    'model',              // path properti Livewire, mis. "items.0.produk_id"
    'options' => [],      // assoc [id => label]
    'selected' => null,   // nilai terpilih saat ini
    'placeholder' => 'Pilih…',
    'label' => null,
    'error' => null,
    'hint' => null,
])

@php
    $optList = collect($options)->map(fn ($label, $id) => ['id' => (string) $id, 'label' => (string) $label])->values()->all();
    $selectedLabel = ($selected !== null && $selected !== '') ? ($options[$selected] ?? '') : '';
@endphp

{{-- Dropdown dengan pencarian (Alpine). Sumber kebenaran = hidden input wire:model.live,
     sehingga label terpilih dirender server (tahan morph Livewire). --}}
<div {{ $attributes->whereStartsWith('wire:') }}
     class="space-y-1.5"
     x-data="{
        open: false,
        q: '',
        options: {{ \Illuminate\Support\Js::from($optList) }},
        get filtered() {
            const s = this.q.trim().toLowerCase();
            return s ? this.options.filter(o => o.label.toLowerCase().includes(s)) : this.options;
        },
        pick(id) {
            this.$refs.hidden.value = id;
            this.$refs.hidden.dispatchEvent(new Event('input'));
            this.open = false; this.q = '';
        },
     }"
     @click.outside="open = false"
     @keydown.escape="open = false">
    @if ($label)
        <label class="block text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <div class="relative">
        <input type="hidden" x-ref="hidden" wire:model.live="{{ $model }}">

        <button type="button"
                @click="open = !open; if (open) $nextTick(() => $refs.q.focus())"
                @class([
                    'flex min-h-[44px] w-full items-center justify-between gap-2 rounded-lg border bg-card px-3 text-left text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand',
                    'border-status-danger' => $error,
                    'border-line' => ! $error,
                ])>
            <span class="truncate {{ $selectedLabel ? 'text-ink' : 'text-ink-muted' }}">{{ $selectedLabel ?: $placeholder }}</span>
            <svg class="h-4 w-4 shrink-0 text-ink-muted" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>

        <div x-show="open" x-cloak x-transition.opacity
             class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-line bg-card shadow-lg">
            <div class="border-b border-line p-2">
                <input type="search" x-model="q" x-ref="q" placeholder="Cari…" @click.stop
                       class="block w-full rounded-md border border-line bg-card px-2.5 py-1.5 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="max-h-56 overflow-y-auto p-1">
                <template x-for="o in filtered" :key="o.id">
                    <button type="button" @click="pick(o.id)"
                            class="block w-full truncate rounded-md px-2.5 py-2 text-left text-sm text-ink hover:bg-page"
                            x-text="o.label"></button>
                </template>
                <template x-if="!filtered.length">
                    <p class="px-2.5 py-3 text-center text-sm text-ink-muted">Tidak ada yang cocok.</p>
                </template>
            </div>
        </div>
    </div>

    @if ($error)
        <p class="text-xs text-status-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif
</div>
