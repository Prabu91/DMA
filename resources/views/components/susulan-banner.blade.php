{{--
    Penanda mode SUSULAN di etalase staf: keranjang sedang dipakai membuat
    order susulan untuk sebuah order induk. Tanpa penanda ini staf bisa lupa
    sedang di mode itu dan memasukkan booking sekolah lain ke keranjang ini.
--}}
@php
    $cart = app(\App\Support\Cart::class);
    // Order::find kena CabangScope — induk di luar jangkauan tidak ditampilkan.
    $induk = $cart->indukId() ? \App\Models\Order::with('sekolah:id,nama')->find($cart->indukId()) : null;
@endphp

@if ($induk)
    <div {{ $attributes->merge(['class' => 'mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/30 bg-brand/5 px-4 py-3']) }}>
        <div class="flex min-w-0 items-start gap-3">
            <x-badge variant="brand" class="mt-0.5 shrink-0">Susulan</x-badge>
            <p class="text-sm text-ink">
                Membuat order susulan untuk
                <span class="font-medium">{{ $induk->booking_code ?? 'order #'.$induk->id }}</span>
                · {{ $induk->sekolah?->nama }}.
                <span class="block text-xs text-ink-muted">Produk yang juga ada di order induk memakai harga induk (terlihat di keranjang).</span>
            </p>
        </div>
        <a href="{{ route('app.keranjang') }}" wire:navigate class="shrink-0 text-sm font-medium text-brand hover:text-brand-hover">Lihat keranjang →</a>
    </div>
@endif
