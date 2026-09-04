@props([
    'options',        // ['nama|asc' => 'Nama A–Z', 'harga|desc' => 'Harga tertinggi', ...]
    'field' => '',
    'dir' => 'asc',
])

{{--
    Pengurut untuk daftar berbentuk kartu (tidak punya header kolom untuk diklik).
    Komponen Livewire pemakainya wajib memakai trait WithSorting — nilai dikirim
    sebagai "field|arah" ke setSort(), yang memvalidasinya lewat sortableColumns().
--}}
@php $current = $field !== '' ? $field.'|'.$dir : array_key_first($options); @endphp

<label {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <span class="shrink-0 text-sm text-ink-muted">Urutkan</span>
    <select wire:change="setSort($event.target.value)"
            class="min-h-[44px] w-full rounded-lg border border-line bg-card py-2 pl-3 pr-9 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
