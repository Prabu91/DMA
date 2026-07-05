{{-- Konten dashboard lintas cabang. Variabel: $data --}}
<div class="space-y-6">
    @include('dashboard.partials.stats', ['stats' => $data['stats']])

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Order per cabang" padding="p-0">
            @forelse ($data['perCabang'] as $cabang)
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$cabang->nama" size="sm" />
                        <div>
                            <div class="text-sm text-ink">{{ $cabang->nama }}</div>
                            <div class="text-xs text-ink-muted">{{ $cabang->kode_area }}</div>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-ink">{{ $cabang->orders_count }}</span>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada cabang.</div>
            @endforelse
        </x-card>

        <x-card title="Master data" subtitle="Akses pengaturan data inti">
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    ['Cabang', 'building', 'cabang.index', ['super_admin']],
                    ['Pengguna', 'users', 'pengguna.index', ['super_admin']],
                    ['Sekolah', 'school', 'sekolah.index', ['super_admin', 'operasional']],
                    ['Kategori', 'tag', 'kategori.index', ['super_admin', 'operasional']],
                    ['Produk', 'product', 'produk.index', ['super_admin', 'operasional']],
                    ['Paket', 'cube', 'paket.index', ['super_admin', 'operasional']],
                    ['Desain', 'photo', 'desain.index', ['super_admin', 'operasional']],
                    ['Free sekolah', 'gift', 'aturan-free.index', ['super_admin', 'operasional']],
                ] as [$label, $iconKey, $routeName, $roles])
                    @if ($routeName && auth()->user()->hasAnyRole($roles))
                        <a href="{{ route($routeName) }}"
                           class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2.5 text-sm text-ink transition-colors hover:bg-page">
                            <svg class="h-5 w-5 shrink-0 text-navy" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($iconKey) }}" />
                            </svg>
                            <span class="truncate">{{ $label }}</span>
                            <svg class="ml-auto h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    @else
                        <span class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2.5 text-sm text-ink-muted"
                              title="Segera hadir">
                            <svg class="h-5 w-5 shrink-0 text-navy" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($iconKey) }}" />
                            </svg>
                            <span class="truncate">{{ $label }}</span>
                            <span class="ml-auto rounded bg-ink/5 px-1.5 py-0.5 text-[10px]">Segera</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </x-card>
    </div>
</div>
