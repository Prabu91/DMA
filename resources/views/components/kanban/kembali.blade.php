{{-- Baris "kembali" di dalam menu bertingkat. --}}
<button type="button" x-on:click="sub = null"
        {{ $attributes->merge(['class' => 'mb-1.5 flex items-center gap-1 rounded px-1 py-1 text-xs font-medium text-ink-muted hover:bg-page hover:text-ink']) }}>
    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14 6l-6 6 6 6" />
    </svg>
    Back
</button>
