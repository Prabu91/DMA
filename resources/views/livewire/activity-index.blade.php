<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Aktivitas'],
    ]" />

    <div class="mb-4">
        <h1 class="text-lg font-medium text-ink">Aktivitas</h1>
        <p class="text-sm text-ink-muted">Riwayat aksi lintas order — siapa melakukan apa & kapan.</p>
    </div>

    {{-- Filter --}}
    @php $ctrl = 'h-9 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand'; @endphp
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-[180px] flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-ink-muted">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            </span>
            <input wire:model.live.debounce.300ms="q" type="search" placeholder="Cari kode / sekolah…"
                   class="h-9 w-full rounded-lg border border-line bg-card pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
        </div>
        <select wire:model.live="action" class="{{ $ctrl }}">
            @foreach ($actionOptions as $v => $t)<option value="{{ $v }}" @selected($action === (string) $v)>{{ $t }}</option>@endforeach
        </select>
        @if ($isAdmin)
            <select wire:model.live="cabangId" class="{{ $ctrl }}">
                <option value="">Semua cabang</option>
                @foreach ($cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($cabangId === (string) $id)>{{ $nama }}</option>@endforeach
            </select>
        @endif
        <div class="flex h-9 items-center gap-1.5 rounded-lg border border-line bg-card px-2.5">
            <span class="text-xs text-ink-muted">Tgl</span>
            <input wire:model.live="dari" type="date" title="Dari" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" title="Sampai" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>
        @if ($q !== '' || $action !== '' || $dari !== '' || $sampai !== '' || $cabangId !== '')
            <button type="button" wire:click="resetFilter" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand hover:bg-brand/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>Reset
            </button>
        @endif
    </div>

    <x-card padding="p-0">
        @forelse ($activities as $a)
            <div class="flex items-start gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <span @class([
                    'mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full',
                    'bg-status-danger' => $a->tone() === 'danger',
                    'bg-status-success' => $a->tone() === 'success',
                    'bg-status-info' => $a->tone() === 'info',
                    'bg-ink-muted/40' => $a->tone() === 'neutral',
                ])></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                        <span class="text-sm font-medium text-ink">{{ $a->label() }}</span>
                        <span class="text-xs text-ink-muted">{{ $a->created_at?->translatedFormat('d M Y · H:i') }}</span>
                    </div>
                    <div class="text-xs text-ink-muted">
                        {{ $a->user?->nama ?? $a->user?->name ?? 'Sistem/Sekolah' }}@if ($a->description) · {{ $a->description }}@endif
                    </div>
                    @if ($a->order)
                        <a href="{{ route('app.order.show', $a->order->id) }}" wire:navigate
                           class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                            {{ $a->order->booking_code ?? 'Order #'.$a->order->id }}
                            <span class="text-ink-muted">· {{ $a->order->sekolah?->nama }} · {{ $a->order->cabang?->nama }}</span>
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-sm text-ink-muted">Belum ada aktivitas yang cocok.</div>
        @endforelse
    </x-card>

    <div class="mt-4">{{ $activities->links() }}</div>
</div>
