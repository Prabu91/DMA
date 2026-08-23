<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Finance'],
        ['label' => 'Transaksi Harian per Event'],
    ]" />

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Transaksi Harian per Event</h1>
            <p class="text-sm text-ink-muted">Semua event pada tanggal terpilih (hari-H). Catat DP langsung; yang belum bayar tetap tercatat.</p>
        </div>
        <div class="flex items-end gap-3">
            <div class="space-y-1">
                <label class="block text-xs text-ink-muted">Tanggal event</label>
                <input wire:model.live="tanggal" type="date" class="h-9 rounded-lg border border-line bg-card px-2.5 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="rounded-lg border border-brand/20 bg-brand/5 px-3 py-1.5 text-center">
                <div class="text-[11px] uppercase tracking-wide text-brand">DP terkumpul</div>
                <div class="text-sm font-bold text-brand-hover">Rp{{ number_format($terkumpul, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    @if ($msg)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $msg }}</div>
    @endif

    <x-table min-width="960px">
        <x-slot:head>
            <x-table.th>Booking</x-table.th>
            <x-table.th>Sekolah</x-table.th>
            <x-table.th>Marketing</x-table.th>
            <x-table.th align="right">Total</x-table.th>
            <x-table.th align="right">DP hari ini</x-table.th>
            <x-table.th align="right">Outstanding</x-table.th>
            <x-table.th>Catat DP</x-table.th>
        </x-slot:head>

        @forelse ($orders as $order)
            @php
                $dpHariIni = $order->pembayaran->filter(fn ($p) => optional($p->tanggal_bayar)->toDateString() === $tanggal)->sum('jumlah');
            @endphp
            <x-table.tr>
                <x-table.td nowrap><span class="font-medium text-ink">{{ $order->booking_code ?? '#'.$order->id }}</span></x-table.td>
                <x-table.td>{{ $order->sekolah?->nama ?? '—' }}</x-table.td>
                <x-table.td muted>{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</x-table.td>
                <x-table.td nowrap align="right" muted>Rp{{ number_format($order->totalSetelahDiskon(), 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" class="{{ $dpHariIni > 0 ? 'text-status-success font-medium' : 'text-ink-muted' }}">
                    {{ $dpHariIni > 0 ? 'Rp'.number_format($dpHariIni, 0, ',', '.') : 'belum' }}
                </x-table.td>
                <x-table.td nowrap align="right" class="font-semibold text-brand-hover">Rp{{ number_format($order->outstanding(), 0, ',', '.') }}</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-2">
                        <input type="number" min="1" wire:model="inputDp.{{ $order->id }}" placeholder="Nominal"
                               class="h-8 w-28 rounded-lg border border-line bg-card px-2 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                        <x-button wire:click="catatDp({{ $order->id }})" size="sm" variant="secondary">Catat</x-button>
                    </div>
                    @error('inputDp.'.$order->id)<p class="mt-1 text-right text-xs text-status-danger">{{ $message }}</p>@enderror
                </x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="7">Tidak ada event pada tanggal ini.</x-table.empty>
        @endforelse
    </x-table>
</div>
