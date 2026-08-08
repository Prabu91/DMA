<div>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-ink">Riwayat booking</h1>
            <p class="text-sm text-ink-muted">Semua pemesanan sekolah Anda.</p>
        </div>
        <x-button :href="route('sekolah.katalog.index')" size="sm" wire:navigate>Booking baru</x-button>
    </div>

    <x-card padding="p-0">
        @forelse ($this->orders as $order)
            <a href="{{ route('sekolah.riwayat.show', $order->id) }}" wire:navigate
               class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0 hover:bg-page">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        @if ($order->booking_code)
                            <span class="text-sm font-bold tracking-wide text-ink">{{ $order->booking_code }}</span>
                        @else
                            <span class="text-sm font-semibold text-ink">Booking #{{ $order->id }}</span>
                            <x-badge variant="pending">Menunggu marketing</x-badge>
                        @endif
                    </div>
                    <div class="mt-0.5 text-xs text-ink-muted">
                        {{ optional($order->tanggal_booking)->translatedFormat('d M Y') }} ·
                        {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa
                    </div>
                </div>
                <div class="text-sm font-extrabold text-navy">Rp{{ number_format($order->total, 0, ',', '.') }}</div>
            </a>
        @empty
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-ink-muted">Belum ada booking.</p>
                <a href="{{ route('sekolah.katalog.index') }}" wire:navigate class="mt-1 inline-block text-sm font-medium text-brand hover:text-brand-hover">Mulai booking</a>
            </div>
        @endforelse
    </x-card>
</div>
