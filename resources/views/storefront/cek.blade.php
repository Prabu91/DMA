<x-storefront-layout>
    <section class="mx-auto max-w-lg px-4 py-12 sm:py-16">
        <div class="mb-6 text-center">
            <div class="text-xs font-extrabold tracking-[0.18em] text-brand">VERIFIKASI BOOKING</div>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-navy-900">Cek Keaslian Reservasi</h1>
        </div>

        @if ($order)
            <div class="overflow-hidden rounded-2xl border border-navy-900/10 bg-white shadow-sm">
                {{-- Header status --}}
                <div class="flex items-center gap-3 border-b border-navy-900/10 bg-emerald-50 px-5 py-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-extrabold text-emerald-700">Booking Terverifikasi</p>
                        <p class="font-mono text-xs text-emerald-700/70">{{ $order->booking_code }}</p>
                    </div>
                </div>

                {{-- Detail --}}
                <dl class="divide-y divide-navy-900/5 px-5 text-sm">
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-navy-900/55">Sekolah</dt>
                        <dd class="text-right font-semibold text-navy-900">{{ $order->sekolah?->nama ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-navy-900/55">Cabang</dt>
                        <dd class="text-right font-semibold text-navy-900">{{ $order->cabang?->nama ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-navy-900/55">Tanggal event</dt>
                        <dd class="text-right font-semibold text-navy-900">
                            {{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('d F Y') : 'Belum dijadwalkan' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-navy-900/55">Jumlah item</dt>
                        <dd class="text-right font-semibold text-navy-900">{{ $order->items_count }} item</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-navy-900/55">Status pembayaran</dt>
                        <dd class="text-right">
                            <span class="inline-flex rounded-full bg-navy-900/5 px-2.5 py-1 text-xs font-bold text-navy-900">
                                {{ \App\Support\OrderStatus::label($order->status) }}
                            </span>
                        </dd>
                    </div>
                    @if ($order->event_status)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-navy-900/55">Status event</dt>
                            <dd class="text-right">
                                <span class="inline-flex rounded-full bg-navy-900/5 px-2.5 py-1 text-xs font-bold text-navy-900">
                                    {{ \App\Support\OrderStatus::label($order->event_status) }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <p class="mt-4 text-center text-xs text-navy-900/45">
                Data resmi dari sistem 8 Mata Air Photography. Untuk pertanyaan, hubungi cabang terkait.
            </p>
        @else
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-8 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-500 text-white">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                </span>
                <p class="mt-3 text-base font-extrabold text-rose-700">Booking Tidak Ditemukan</p>
                <p class="mt-1 font-mono text-xs text-rose-700/70">{{ $booking }}</p>
                <p class="mt-3 text-sm text-rose-700/80">Kode ini tidak terdaftar di sistem DMA. Pastikan QR yang dipindai benar.</p>
            </div>
        @endif

        <div class="mt-6 text-center">
            <a href="{{ route('storefront.home') }}" class="text-sm font-bold text-brand hover:text-brand-hover">← Kembali ke beranda</a>
        </div>
    </section>
</x-storefront-layout>
