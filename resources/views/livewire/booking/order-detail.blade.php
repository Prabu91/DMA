@php $order = $this->order; @endphp
<div>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Booking tersimpan</h1>
            <p class="text-sm text-ink-muted">{{ $order->sekolah?->nama }} · {{ optional($order->tanggal_booking)->translatedFormat('d M Y H:i') }}</p>
        </div>
        <a href="{{ $this->kembaliUrl() }}" wire:navigate class="text-sm text-ink-muted hover:text-ink">Selesai</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Status --}}
            <x-card>
                @if ($order->booking_code)
                    <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <div class="rounded-lg border border-line bg-white p-2">{!! $this->qrSvg() !!}</div>
                        <div>
                            <x-badge variant="success">Kode booking</x-badge>
                            <div class="mt-2 text-lg font-medium tracking-wide text-ink">{{ $order->booking_code }}</div>
                            <div class="mt-3 flex gap-2">
                                <x-button :href="$this->pdfUrl()" size="sm" target="_blank">Unduh / cetak PDF</x-button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <x-badge variant="pending">Menunggu penugasan marketing</x-badge>
                    </div>
                    <p class="mt-2 text-xs text-ink-muted">Kode booking &amp; QR dibuat setelah marketing ditugaskan.</p>
                @endif
            </x-card>

            {{-- Item --}}
            <x-card title="Item" padding="p-0">
                @foreach ($this->paidItems as $item)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-badge :variant="$item->tipe_item === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($item->tipe_item) }}</x-badge>
                                <span class="truncate text-sm text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-muted">
                                @if ($item->desain) Desain {{ $item->desain->kode }} · @endif
                                @if ($item->opsi_ukuran) {{ $item->opsi_ukuran }} · @endif
                                {{ $item->qty }} × Rp{{ number_format($item->harga, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-sm font-medium text-ink">Rp{{ number_format($item->harga * $item->qty, 0, ',', '.') }}</div>
                    </div>
                @endforeach

                @foreach ($this->freeItems as $item)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="flex items-center gap-2">
                            <x-badge variant="success">Free</x-badge>
                            <span class="text-sm text-ink">{{ $item->produk?->nama ?? 'Produk' }}</span>
                            @if ($item->opsi_ukuran)<span class="text-xs text-ink-muted">{{ $item->opsi_ukuran }}</span>@endif
                            <span class="text-xs text-ink-muted">×{{ $item->qty }}</span>
                        </div>
                        <div class="text-sm font-medium text-status-success">Rp0</div>
                    </div>
                @endforeach
            </x-card>
        </div>

        <div>
            <x-card title="Ringkasan">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-muted">Jumlah siswa</dt><dd class="text-ink">{{ $order->jumlah_siswa }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-muted">Item gratis</dt><dd class="text-status-success">{{ $this->freeItems->count() }} item</dd></div>
                    <div class="flex justify-between border-t border-line pt-2 text-base"><dt class="font-medium text-ink">Total</dt><dd class="font-medium text-ink">Rp{{ number_format($order->total, 0, ',', '.') }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</div>
