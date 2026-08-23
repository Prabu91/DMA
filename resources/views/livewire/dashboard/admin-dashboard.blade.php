<div>
    @php
        $ctrl = 'h-9 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand';
        $today = now()->toDateString();
        $plus7 = now()->addDays(7)->toDateString();
    @endphp

    {{-- Filter --}}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        @if ($isAdmin)
            <select wire:model.live="cabangId" class="{{ $ctrl }}">
                <option value="">Semua cabang</option>
                @foreach ($cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($cabangId === (string) $id)>{{ $nama }}</option>@endforeach
            </select>
        @endif
        <div class="inline-flex h-9 items-center rounded-lg border border-line bg-card p-0.5 text-sm">
            <button type="button" wire:click="$set('basis', 'booking')" @class(['rounded-md px-2.5 py-1', 'bg-brand text-white' => $basis === 'booking', 'text-ink-muted hover:text-ink' => $basis !== 'booking'])>Order masuk</button>
            <button type="button" wire:click="$set('basis', 'event')" @class(['rounded-md px-2.5 py-1', 'bg-brand text-white' => $basis === 'event', 'text-ink-muted hover:text-ink' => $basis !== 'event'])>Tgl event</button>
        </div>
        <div class="flex h-9 items-center gap-1.5 rounded-lg border border-line bg-card px-2.5">
            <span class="text-xs text-ink-muted">Periode</span>
            <input wire:model.live="dari" type="date" title="Dari" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
            <span class="text-ink-muted">–</span>
            <input wire:model.live="sampai" type="date" title="Sampai" class="border-0 bg-transparent p-0 text-sm text-ink focus:outline-none focus:ring-0">
        </div>
        @if ($cabangId !== '' || $dari !== '' || $sampai !== '' || $basis !== 'booking')
            <button type="button" wire:click="resetFilter" class="inline-flex h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-medium text-brand hover:bg-brand/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>Reset
            </button>
        @endif
    </div>

    {{-- Perlu tindakan (kondisi terkini) --}}
    <div class="mb-2 flex items-center gap-2">
        <h2 class="text-sm font-medium text-ink">Perlu tindakan</h2>
        <span class="text-xs text-ink-muted">· kondisi terkini{{ $cabangId !== '' ? ' — '.($cabangOptions[$cabangId] ?? '') : '' }}</span>
    </div>
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php
            $cards = [
                ['label' => 'Belum di-assign', 'value' => $perluTindakan['belum_assign'], 'tone' => 'pending', 'icon' => 'inbox', 'url' => route('app.kotak-masuk')],
                ['label' => 'Event terlewat', 'value' => $perluTindakan['terlewat'], 'tone' => 'danger', 'icon' => 'clock', 'url' => route('app.order.index', ['tahap' => 'terlewat'])],
                ['label' => 'Menunggu DP', 'value' => $perluTindakan['menunggu_dp'], 'tone' => 'info', 'icon' => 'order', 'url' => route('app.order.index', ['status' => 'baru'])],
                ['label' => 'Event minggu ini', 'value' => $perluTindakan['event_minggu_ini'], 'tone' => 'brand', 'icon' => 'calendar', 'url' => route('app.order.index', ['dari' => $today, 'sampai' => $plus7])],
            ];
            $toneMap = [
                'danger' => 'bg-status-danger/10 text-status-danger',
                'pending' => 'bg-status-pending/10 text-status-pending',
                'info' => 'bg-status-info/10 text-status-info',
                'brand' => 'bg-brand/10 text-brand',
            ];
        @endphp
        @foreach ($cards as $c)
            <a href="{{ $c['url'] }}" wire:navigate
               class="group flex items-center gap-3 rounded-xl border border-line bg-card p-4 transition-colors hover:border-brand/40">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg {{ $toneMap[$c['tone']] }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($c['icon']) }}" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="text-2xl font-extrabold leading-none text-ink">{{ $c['value'] }}</div>
                    <div class="mt-1 truncate text-xs text-ink-muted">{{ $c['label'] }}</div>
                </div>
                <svg class="ml-auto h-4 w-4 shrink-0 text-ink-muted/50 transition-colors group-hover:text-brand" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
        @endforeach
    </div>

    {{-- Ringkasan periode --}}
    <div class="mb-6 grid grid-cols-3 gap-3 sm:gap-4">
        <x-stat-card label="Order" :value="$summary['order']" :icon="\App\Support\Icons::path('order')" accent="brand" :hint="$basis === 'event' ? 'Berdasar tgl event' : 'Order masuk'" />
        <x-stat-card label="Booking aktif" :value="$summary['aktif']" :icon="\App\Support\Icons::path('clock')" accent="pending" />
        <x-stat-card label="Lunas" :value="$summary['lunas']" :icon="\App\Support\Icons::path('check')" accent="success" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Kiri: agenda + per cabang --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Agenda event terdekat --}}
            <x-card title="Agenda event terdekat" padding="p-0">
                <x-slot name="actions"><a href="{{ route('app.order.index', ['eventStatus' => 'dijadwalkan']) }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-hover">Semua →</a></x-slot>
                @forelse ($agenda as $o)
                    <a href="{{ route('app.event.show', $o->id) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-page">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @php $cd = $o->eventCountdown(); @endphp
                                @if ($cd)<x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>@endif
                                <span class="truncate text-sm font-medium text-ink">{{ $o->sekolah?->nama ?? 'Tanpa sekolah' }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-xs text-ink-muted">
                                {{ $o->tanggal_event->translatedFormat('l, d M Y') }}@if ($o->jam_event) · {{ $o->jam_event }}@endif · {{ $o->cabang?->nama }}
                                · Tim: {{ $o->timEvent->isEmpty() ? 'belum ada' : $o->timEvent->count().' orang' }}
                            </div>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-ink-muted">Tak ada event terjadwal.</div>
                @endforelse
            </x-card>

            {{-- Per cabang --}}
            @if ($perCabang->isNotEmpty())
                <x-card title="Per cabang">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($perCabang as $row)
                            <button type="button" wire:click="$set('cabangId', '{{ $row['cabang']->id }}')"
                                @class([
                                    'rounded-xl border p-3 text-left transition-colors',
                                    'border-brand ring-1 ring-brand/30' => $cabangId === (string) $row['cabang']->id,
                                    'border-line hover:border-brand/40' => $cabangId !== (string) $row['cabang']->id,
                                ])>
                                <div class="flex items-center gap-2">
                                    <x-avatar :name="$row['cabang']->nama" size="sm" />
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-bold text-ink">{{ $row['cabang']->nama }}</div>
                                        <div class="text-xs text-ink-muted">{{ $row['cabang']->kode_area }}</div>
                                    </div>
                                </div>
                                <div class="mt-2 flex gap-3 text-xs text-ink-muted">
                                    <span><b class="text-ink">{{ $row['order'] }}</b> order</span>
                                    <span><b class="text-status-pending">{{ $row['aktif'] }}</b> aktif</span>
                                    <span><b class="text-status-success">{{ $row['lunas'] }}</b> lunas</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </x-card>
            @endif

            {{-- Tren order masuk --}}
            <x-card title="Tren order masuk" subtitle="8 minggu terakhir">
                <div class="flex items-end justify-between gap-2" style="height:120px">
                    @foreach ($tren as $t)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex w-full flex-1 items-end">
                                <div class="w-full rounded-t bg-brand/70" style="height: {{ max(4, (int) round($t['count'] / $trenMax * 100)) }}%" title="{{ $t['count'] }} order"></div>
                            </div>
                            <span class="text-[10px] text-ink-muted">{{ $t['label'] }}</span>
                            <span class="text-[10px] font-semibold text-ink">{{ $t['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- Kanan: kinerja + aktivitas --}}
        <div class="space-y-6">
            {{-- Kinerja marketing --}}
            <x-card title="Kinerja marketing" subtitle="order pada periode">
                @forelse ($kinerja as $k)
                    <div class="mb-3 last:mb-0">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-sm text-ink">{{ $k['nama'] }}</span>
                            <span class="shrink-0 text-xs text-ink-muted"><b class="text-ink">{{ $k['total'] }}</b> · {{ $k['lunas'] }} lunas</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-page">
                            <div class="h-full rounded-full bg-brand" style="width: {{ max(4, (int) round($k['total'] / $kinerjaMax * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">Belum ada order dengan marketing pada periode ini.</p>
                @endforelse
            </x-card>

            {{-- Aktivitas terbaru --}}
            <x-card title="Aktivitas terbaru" padding="p-0">
                <x-slot name="actions"><a href="{{ route('app.aktivitas') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-hover">Semua →</a></x-slot>
                @forelse ($aktivitas as $a)
                    <div class="flex items-start gap-2.5 border-b border-line px-5 py-2.5 last:border-b-0">
                        <span @class([
                            'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                            'bg-status-danger' => $a->tone() === 'danger',
                            'bg-status-success' => $a->tone() === 'success',
                            'bg-status-info' => $a->tone() === 'info',
                            'bg-ink-muted/40' => $a->tone() === 'neutral',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm text-ink">{{ $a->label() }}</span>
                                <span class="shrink-0 text-[11px] text-ink-muted">{{ $a->created_at?->diffForHumans(null, true) }}</span>
                            </div>
                            <div class="truncate text-xs text-ink-muted">
                                {{ $a->user?->nama ?? $a->user?->name ?? 'Sistem/Sekolah' }}@if ($a->order) · {{ $a->order->booking_code ?? 'Order #'.$a->order->id }}@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-ink-muted">Belum ada aktivitas.</div>
                @endforelse
            </x-card>
        </div>
    </div>
</div>
