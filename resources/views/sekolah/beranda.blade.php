<x-sekolah-layout>
    @php $sekolah = auth('sekolah')->user(); @endphp

    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Selamat datang, {{ $sekolah->nama }}</h1>
        <p class="text-sm text-ink-muted">Portal booking sekolah DMA.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-card title="Identitas sekolah">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">ID sekolah</dt>
                    <dd class="text-ink">{{ $sekolah->id_sekolah }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">Kota</dt>
                    <dd class="text-ink">{{ $sekolah->kota ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">PIC</dt>
                    <dd class="text-ink">{{ $sekolah->pic_sekolah ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Booking">
            <p class="text-sm text-ink-muted">Jelajahi paket &amp; produk, lalu buat pemesanan foto sekolah Anda.</p>
            <div class="mt-4">
                <x-button :href="route('sekolah.katalog.index')" wire:navigate>Buka katalog</x-button>
            </div>
        </x-card>
    </div>
</x-sekolah-layout>
