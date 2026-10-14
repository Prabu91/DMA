{{-- Tombol bintang satu board. Dipakai di panel Switch boards (grid & daftar). --}}
@props(['board'])

@php $aktif = (bool) ($board->saya_bintang ?? false); @endphp

<button type="button" wire:click="toggleBintang({{ $board->id }})"
        title="{{ $aktif ? 'Remove from starred' : 'Add to starred' }}"
        aria-pressed="{{ $aktif ? 'true' : 'false' }}"
        aria-label="{{ $aktif ? 'Remove' : 'Add' }} {{ $board->nama }} {{ $aktif ? 'from' : 'to' }} starred"
        {{ $attributes->class([
            'flex items-center justify-center rounded transition',
            'text-[#E2B203]' => $aktif,
            'text-ink-muted opacity-0 hover:text-[#E2B203] focus:opacity-100 group-hover:opacity-100' => ! $aktif,
        ]) }}>
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $aktif ? 'currentColor' : 'none' }}"
         stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" />
    </svg>
</button>
