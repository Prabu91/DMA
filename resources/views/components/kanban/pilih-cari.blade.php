@props([
    'pilihan' => [],          // [['nilai' => 1, 'teks' => 'Antrian', 'petunjuk' => 'opsional'], …]
    'onpilih' => '',          // ekspresi Alpine; pakai p.nilai sebagai nilai terpilih
    'cari' => 'Search…',
    'kosong' => 'No match.',
    'tinggi' => 'max-h-56',
])

{{-- Pemilih berdaftar panjang: diketik dulu, bukan digulung satu per satu. --}}
<div {{ $attributes->merge(['class' => 'mt-1']) }}
     x-data="{
        q: '',
        pilihan: @js(collect($pilihan)->values()),
        get hasil() {
            const q = this.q.trim().toLowerCase();
            return q === '' ? this.pilihan : this.pilihan.filter((p) => (p.teks + ' ' + (p.petunjuk ?? '')).toLowerCase().includes(q));
        },
     }">
    <input type="search" x-model="q" placeholder="{{ $cari }}" aria-label="{{ $cari }}"
           x-on:keydown.enter.prevent="if (hasil.length) { const p = hasil[0]; {{ $onpilih }} }"
           class="block min-h-[34px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">

    <div class="mt-1 {{ $tinggi }} overflow-y-auto">
        <template x-for="p in hasil" :key="p.nilai">
            <button type="button" x-on:click="{{ $onpilih }}"
                    class="block w-full truncate rounded px-2 py-1.5 text-left text-sm hover:bg-page">
                <span x-text="p.teks"></span>
                <span class="text-xs text-ink-muted" x-show="p.petunjuk" x-text="p.petunjuk"></span>
            </button>
        </template>
        <p x-show="hasil.length === 0" class="px-2 py-2 text-xs text-ink-muted">{{ $kosong }}</p>
    </div>
</div>
