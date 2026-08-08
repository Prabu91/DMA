<x-storefront-layout>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        @if ($cabangBelumDitetapkan)
            {{-- Cabang sekolah belum ditetapkan → checkout diblokir (admin assign dulu). --}}
            <x-card>
                <div class="mx-auto max-w-md py-8 text-center">
                    <span class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-status-pending/10 text-status-pending">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('building') }}" />
                        </svg>
                    </span>
                    <h1 class="text-lg font-medium text-ink">Cabang belum ditetapkan</h1>
                    <p class="mt-2 text-sm text-ink-muted">
                        Sekolah Anda belum terhubung ke cabang DMA, sehingga pesanan belum dapat diproses.
                        Silakan hubungi admin untuk menetapkan cabang terlebih dahulu — setelah itu Anda bisa melanjutkan checkout.
                    </p>
                    <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                        <x-button :href="route('storefront.keranjang')" variant="secondary">Kembali ke keranjang</x-button>
                        <x-button :href="route('sekolah.riwayat.index')">Lihat pesanan saya</x-button>
                    </div>
                </div>
            </x-card>
        @else
            <livewire:booking.review konteks="publik" />
        @endif
    </div>
</x-storefront-layout>
