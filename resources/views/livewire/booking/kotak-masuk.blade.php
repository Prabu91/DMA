<div>
    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Kotak masuk</h1>
        <p class="text-sm text-ink-muted">Order booking mandiri sekolah yang menunggu penugasan marketing.</p>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    {{-- Tab --}}
    <div class="mb-4 inline-flex rounded-lg border border-line bg-card p-1 text-sm">
        <button type="button" wire:click="$set('tampil', 'baru')"
            @class(['rounded-md px-3 py-1.5', 'bg-brand text-white' => $tampil === 'baru', 'text-ink-muted hover:text-ink' => $tampil !== 'baru'])>
            Belum ditugaskan
        </button>
        <button type="button" wire:click="$set('tampil', 'ditugaskan')"
            @class(['rounded-md px-3 py-1.5', 'bg-brand text-white' => $tampil === 'ditugaskan', 'text-ink-muted hover:text-ink' => $tampil !== 'ditugaskan'])>
            Sudah ditugaskan
        </button>
    </div>

    <x-card padding="p-0">
        @forelse ($this->orders as $order)
            <div wire:key="order-{{ $order->id }}" class="flex flex-col gap-3 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-ink">Booking #{{ $order->id }}</span>
                        <span class="text-sm text-ink">· {{ $order->sekolah?->nama }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-muted">
                        {{ $order->cabang?->nama }} ·
                        {{ optional($order->tanggal_booking)->translatedFormat('d M Y') }} ·
                        {{ $order->items_count }} item · {{ $order->jumlah_siswa }} siswa ·
                        Rp{{ number_format($order->total, 0, ',', '.') }}
                        @if ($tampil === 'ditugaskan')
                            · Marketing: <span class="text-ink">{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($tampil === 'baru' && $this->isMarketing)
                        <x-button wire:click="ambil({{ $order->id }})" size="sm">Ambil</x-button>
                    @endif

                    @if ($this->isAdmin)
                        @php $opsi = $this->marketingByCabang[$order->cabang_id] ?? []; @endphp
                        <select wire:model="pilihMarketing.{{ $order->id }}"
                                class="min-h-[36px] rounded-lg border border-line bg-card px-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30">
                            <option value="">— Marketing —</option>
                            @foreach ($opsi as $uid => $nama)
                                <option value="{{ $uid }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        @if ($tampil === 'baru')
                            <x-button wire:click="tugaskan({{ $order->id }})" variant="secondary" size="sm">Tugaskan</x-button>
                        @else
                            <x-button wire:click="reassign({{ $order->id }})" variant="secondary" size="sm">Ubah</x-button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">
                {{ $tampil === 'baru' ? 'Tidak ada order yang menunggu penugasan.' : 'Belum ada order yang ditugaskan.' }}
            </div>
        @endforelse
    </x-card>
</div>
