{{-- Formulir kartu baru. Dipakai di atas list (tombol + di kepala) maupun di bawahnya. --}}
<form wire:submit="tambahKartu" class="px-2 pb-2 pt-1" x-data x-init="$nextTick(() => $refs.judul.focus())">
    <label for="judul-kartu-{{ $kolom->id }}" class="sr-only">Card title</label>
    <textarea id="judul-kartu-{{ $kolom->id }}" x-ref="judul" wire:model="judulKartuBaru" rows="2"
              placeholder="Enter a card title…"
              x-on:keydown.enter.prevent="$wire.tambahKartu().then(() => $refs.judul?.focus())"
              x-on:keydown.escape="$wire.batalTambahKartu()"
              class="block w-full resize-none rounded-lg border-0 text-sm shadow-[0_1px_1px_rgba(9,30,66,.25)] focus:ring-2 focus:ring-brand"></textarea>
    @error('judulKartuBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
    <div class="mt-2 flex items-center gap-1">
        <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Add card</button>
        <button type="button" wire:click="batalTambahKartu" aria-label="Cancel" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-line">✕</button>
        @if ($this->templat->isNotEmpty())
            <div class="relative ml-auto" x-data="{ buka: false }">
                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka"
                        class="h-9 rounded-md px-2 text-sm text-ink-muted hover:bg-line hover:text-ink">From template</button>
                <ul x-show="buka" x-cloak x-on:click.outside="buka = false"
                    x-effect="buka && $nextTick(() => { const r = $el.getBoundingClientRect(); $el.style.maxHeight = Math.max(180, window.innerHeight - r.top - 88) + 'px' })"
                    class="gulir-gelap absolute right-0 z-40 mt-1 w-56 overflow-y-auto rounded-xl border border-line bg-card py-1 text-sm shadow-lg">
                    @foreach ($this->templat as $tpl)
                        <li wire:key="tpl-{{ $kolom->id }}-{{ $tpl->id }}">
                            <button type="button" x-on:click="buka = false" wire:click="dariTemplat({{ $tpl->id }}, {{ $kolom->id }})"
                                    class="block w-full truncate px-3 py-2 text-left hover:bg-page">{{ $tpl->judul }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</form>
