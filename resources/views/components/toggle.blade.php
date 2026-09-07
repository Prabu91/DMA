@props([
    'label' => null,
    'hint' => null,
    'align' => 'center',   // 'center' utk baris pendek, 'start' bila ada hint panjang
])

{{--
    Saklar on/off. Dipakai untuk pilihan biner (menyala/mati) — BUKAN untuk
    daftar pilih-banyak seperti tim event atau kecamatan; yang itu tetap checkbox.

    Murni CSS: <input> tetap checkbox sungguhan (jadi wire:model, keyboard, dan
    pembaca layar bekerja apa adanya), hanya disembunyikan dan digambar ulang.
    Track & knob sengaja jadi SAUDARA input, bukan anaknya, karena varian
    peer-checked memakai kombinator saudara (~).
--}}
<label {{ $attributes->only(['class', 'title'])->merge([
    'class' => 'flex cursor-pointer gap-3 '.($align === 'start' ? 'items-start' : 'items-center'),
]) }}>
    <span class="relative inline-flex h-6 w-11 shrink-0 items-center {{ $align === 'start' ? 'mt-0.5' : '' }}">
        <input type="checkbox" {{ $attributes->except(['class', 'title']) }} class="peer sr-only">
        <span class="absolute inset-0 rounded-full bg-line transition-colors peer-checked:bg-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand/40"></span>
        <span class="absolute left-0.5 h-5 w-5 rounded-full bg-card shadow transition-transform peer-checked:translate-x-5"></span>
    </span>

    @if ($label || $hint)
        <span class="min-w-0">
            @if ($label)
                <span class="block text-sm font-medium text-ink">{{ $label }}</span>
            @endif
            @if ($hint)
                <span class="mt-0.5 block text-xs text-ink-muted">{{ $hint }}</span>
            @endif
        </span>
    @endif
</label>
