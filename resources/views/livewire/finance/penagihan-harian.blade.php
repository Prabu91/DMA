<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Finance'],
        ['label' => 'Penagihan Harian'],
    ]" />

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Penagihan Harian</h1>
            <p class="text-sm text-ink-muted">Pembayaran sisa tagihan / DP telat pada tanggal terpilih (di luar hari-H event).</p>
        </div>
        <div class="flex items-end gap-3">
            <div class="space-y-1">
                <label class="block text-xs text-ink-muted">Tanggal</label>
                <input wire:model.live="tanggal" type="date" class="h-9 rounded-lg border border-line bg-card px-2.5 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
            </div>
            <div class="rounded-lg border border-brand/20 bg-brand/5 px-3 py-1.5 text-center">
                <div class="text-[11px] uppercase tracking-wide text-brand">Terkumpul</div>
                <div class="text-sm font-bold text-brand-hover">Rp{{ number_format($totalTerkumpul, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <x-table min-width="900px">
        <x-slot:head>
            <x-table.th>Waktu</x-table.th>
            <x-table.th>Booking</x-table.th>
            <x-table.th>Sekolah</x-table.th>
            <x-table.th>Marketing</x-table.th>
            <x-table.th>Jenis</x-table.th>
            <x-table.th align="right">Jumlah</x-table.th>
            <x-table.th align="right">Outstanding</x-table.th>
            <x-table.th>Dicatat oleh</x-table.th>
        </x-slot:head>

        @forelse ($rows as $p)
            <x-table.tr>
                <x-table.td nowrap muted>{{ $p->created_at->translatedFormat('H:i') }}</x-table.td>
                <x-table.td nowrap><span class="font-medium text-ink">{{ $p->order?->booking_code ?? '#'.$p->order_id }}</span></x-table.td>
                <x-table.td>{{ $p->order?->sekolah?->nama ?? '—' }}</x-table.td>
                <x-table.td muted>{{ $p->order?->marketing?->nama ?? $p->order?->marketing?->name ?? '—' }}</x-table.td>
                <x-table.td><x-badge :variant="$p->jenis === 'pelunasan' ? 'success' : 'info'">{{ \App\Models\OrderPembayaran::JENIS[$p->jenis] ?? $p->jenis }}</x-badge></x-table.td>
                <x-table.td nowrap align="right" class="font-medium">Rp{{ number_format($p->jumlah, 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" muted>Rp{{ number_format($p->order?->outstanding() ?? 0, 0, ',', '.') }}</x-table.td>
                <x-table.td muted>{{ $p->pencatat?->nama ?? $p->pencatat?->name ?? '—' }}</x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="8">Tidak ada penagihan pada tanggal ini.</x-table.empty>
        @endforelse
    </x-table>
</div>
