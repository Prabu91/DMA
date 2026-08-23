<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Report order'],
    ]" />

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Report order</h1>
            <p class="text-sm text-ink-muted">Penjualan per produk — satu baris per item order. Hanya order yang sudah ditugaskan marketing.</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="rounded-lg border border-line bg-card px-3 py-1.5 text-center">
                <div class="text-[11px] uppercase tracking-wide text-ink-muted">Baris</div>
                <div class="text-sm font-semibold text-ink">{{ number_format($totalBaris, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-lg border border-line bg-brand/5 px-3 py-1.5 text-center">
                <div class="text-[11px] uppercase tracking-wide text-brand">Total qty</div>
                <div class="text-sm font-bold text-brand-hover">{{ number_format($totalQty, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-lg border border-line bg-brand/5 px-3 py-1.5 text-center">
                <div class="text-[11px] uppercase tracking-wide text-brand">Total nominal</div>
                <div class="text-sm font-bold text-brand-hover">Rp{{ number_format($totalNominal, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    @php $ctrl = 'h-9 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[200px] flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="q" type="search" placeholder="Cari produk / sekolah / kode booking…"
                   class="h-9 w-full rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        </div>

        <select wire:model.live="produkId" class="{{ $ctrl }} max-w-[220px]">
            <option value="">Semua produk</option>
            @foreach ($produkOptions as $id => $nama)<option value="{{ $id }}" @selected($produkId === (string) $id)>{{ $nama }}</option>@endforeach
        </select>

        <select wire:model.live="cabangId" class="{{ $ctrl }}">
            <option value="">Semua cabang</option>
            @foreach ($cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($cabangId === (string) $id)>{{ $nama }}</option>@endforeach
        </select>

        <select wire:model.live="jenis" class="{{ $ctrl }}">
            <option value="" @selected($jenis === '')>Semua item</option>
            <option value="berbayar" @selected($jenis === 'berbayar')>Berbayar</option>
            <option value="free" @selected($jenis === 'free')>Free</option>
        </select>

        <div class="flex h-9 items-center gap-1.5 rounded-lg border border-line bg-card px-2.5">
            <span class="text-xs text-ink-muted">Booking</span>
            <input wire:model.live="dari" type="date" title="Tanggal booking dari" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" title="Tanggal booking sampai" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>

        @if ($this->adaFilter())
            <button wire:click="resetFilter" class="h-9 rounded-lg border border-line bg-card px-3 text-sm text-ink-muted hover:text-ink">Reset</button>
        @endif
    </div>

    {{-- Tabel --}}
    <x-table min-width="920px">
        <x-slot:head>
            <x-table.th sortable field="booking" :sort="$sortField" :dir="$sortDir">Booking</x-table.th>
            <x-table.th sortable field="tanggal" :sort="$sortField" :dir="$sortDir">Tgl booking</x-table.th>
            <x-table.th sortable field="marketing" :sort="$sortField" :dir="$sortDir">Marketing</x-table.th>
            <x-table.th sortable field="id_sekolah" :sort="$sortField" :dir="$sortDir">ID sekolah</x-table.th>
            <x-table.th sortable field="sekolah" :sort="$sortField" :dir="$sortDir">Nama sekolah</x-table.th>
            <x-table.th>Alamat</x-table.th>
            <x-table.th sortable field="item" :sort="$sortField" :dir="$sortDir">Produk / item</x-table.th>
            <x-table.th sortable field="qty" :sort="$sortField" :dir="$sortDir" align="right">Qty</x-table.th>
            <x-table.th sortable field="nominal" :sort="$sortField" :dir="$sortDir" align="right">Nominal</x-table.th>
        </x-slot:head>

        @forelse ($rows as $row)
            <x-table.tr>
                <x-table.td nowrap>
                    <a href="{{ route('app.order.show', $row->order_id) }}" wire:navigate class="font-medium text-brand hover:text-brand-hover">
                        {{ $row->booking_code ?? '#'.$row->order_id }}
                    </a>
                </x-table.td>
                <x-table.td nowrap muted>{{ $row->tanggal_booking ? \Illuminate\Support\Carbon::parse($row->tanggal_booking)->translatedFormat('d M Y') : '—' }}</x-table.td>
                <x-table.td nowrap>{{ $row->marketing_nama ?? '—' }}</x-table.td>
                <x-table.td nowrap>
                    <span class="rounded-md bg-ink/5 px-1.5 py-0.5 text-xs text-ink-muted">{{ $row->id_sekolah ?? '—' }}</span>
                </x-table.td>
                <x-table.td>{{ $row->sekolah_nama ?? '—' }}</x-table.td>
                <x-table.td muted class="max-w-[220px]">
                    <span class="block truncate" title="{{ $row->sekolah_alamat }}">{{ $row->sekolah_alamat ?: '—' }}</span>
                </x-table.td>
                <x-table.td>
                    <span>{{ $row->item_nama ?? '—' }}</span>
                    @if ($row->tipe_item === 'paket')<x-badge variant="brand" class="ml-1">Paket</x-badge>@endif
                    @if ($row->is_free)<x-badge variant="success" class="ml-1">Free</x-badge>@endif
                </x-table.td>
                <x-table.td nowrap align="right" class="font-semibold">{{ number_format($row->qty, 0, ',', '.') }}</x-table.td>
                <x-table.td nowrap align="right" class="font-semibold text-brand-hover">Rp{{ number_format($row->nominal, 0, ',', '.') }}</x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="9">Tidak ada data untuk filter ini.</x-table.empty>
        @endforelse
    </x-table>

    {{-- Paginasi + per halaman --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm text-ink-muted">
            <span>Per halaman</span>
            <select wire:model.live="perPage" class="{{ $ctrl }} h-8">
                @foreach ([25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
            </select>
        </div>
        <div>{{ $rows->links() }}</div>
    </div>
</div>
