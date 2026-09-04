{{--
    Pembungkus daftar mendatar + panah geser untuk desktop.

    Di ponsel daftarnya cukup digeser dengan jari, jadi panahnya disembunyikan.
    Di desktop scrollbar-nya sengaja disembunyikan demi tampilan — tanpa panah,
    daftar sama sekali tidak bisa digerakkan dengan mouse.

    Panah hanya muncul di sisi yang masih ada isinya. ResizeObserver dipakai
    (bukan event resize jendela) supaya perhitungannya juga jalan saat slider
    baru ditampilkan, mis. ketika dibungkus x-show.
--}}
<div {{ $attributes->merge(['class' => 'relative']) }}
     x-data="{
        kiri: false,
        kanan: false,
        perbarui() {
            const t = $refs.track;
            if (! t) return;
            this.kiri = t.scrollLeft > 4;
            this.kanan = Math.ceil(t.scrollLeft + t.clientWidth) < t.scrollWidth - 4;
        },
        geser(arah) {
            const t = $refs.track;
            t.scrollBy({ left: arah * Math.max(t.clientWidth * 0.8, 200), behavior: 'smooth' });
        },
     }"
     x-init="$nextTick(() => perbarui()); new ResizeObserver(() => perbarui()).observe($refs.track)">

    <button type="button" x-show="kiri" x-cloak x-on:click="geser(-1)" aria-label="Geser ke kiri"
            class="absolute left-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-line bg-card text-ink shadow-md transition hover:border-brand/60 hover:text-brand sm:grid">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
    </button>

    <div x-ref="track" x-on:scroll.passive="perbarui()"
         class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {{ $slot }}
    </div>

    <button type="button" x-show="kanan" x-cloak x-on:click="geser(1)" aria-label="Geser ke kanan"
            class="absolute right-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-line bg-card text-ink shadow-md transition hover:border-brand/60 hover:text-brand sm:grid">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
    </button>
</div>
