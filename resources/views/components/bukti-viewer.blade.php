{{-- Penampil bukti bayar dalam modal (satu instance per halaman).
     Buka lewat: x-on:click="$dispatch('show-bukti', { url: '...' })" --}}
<div
    x-data="{ open: false, url: '' }"
    x-on:show-bukti.window="url = $event.detail.url; open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    style="display:none"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-navy-900/70 backdrop-blur-sm" x-on:click="open = false"></div>

    <div x-show="open"
         x-transition
         class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <h3 class="text-sm font-semibold text-ink">Bukti pembayaran</h3>
            <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-ink-muted hover:bg-line hover:text-ink">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="max-h-[70vh] overflow-auto bg-page p-3 text-center">
            <img :src="url" alt="Bukti pembayaran" class="mx-auto max-h-[65vh] w-auto rounded-lg" />
        </div>
        <div class="flex justify-end gap-2 border-t border-line px-4 py-3">
            <a :href="url" target="_blank" rel="noopener" class="text-sm font-medium text-brand hover:text-brand-hover">Buka di tab baru</a>
        </div>
    </div>
</div>
