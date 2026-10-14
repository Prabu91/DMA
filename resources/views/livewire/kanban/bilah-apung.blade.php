@php
    use App\Support\Kanban\Warna;

    $tujuan = $this->boardTujuan;
    $diBoard = $tujuan && $boardId === $tujuan->id;
@endphp

{{-- Bilah mengambang: selalu ada di bawah layar, di halaman kanban mana pun. --}}
<div class="bilah-apung pointer-events-none fixed inset-x-0 bottom-0 z-30 flex justify-center px-3"
     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
    <div class="pointer-events-auto relative" x-data
         x-init="if ($store.papan.sematkan && ! $wire.buka) $wire.togglePanel()"
         x-on:keydown.escape.window="if (! $store.papan.sematkan) $wire.tutupPanel()">

        {{-- Panel pindah board: grid bergambar atau daftar ringkas --}}
        @if ($buka)
            <div x-on:click.outside="if (! $store.papan.sematkan) $wire.tutupPanel()"
                 x-data="{
                    tata: 'grid',
                    init() {
                        try { this.tata = localStorage.getItem('kanban:tata-board') ?? 'grid' } catch (e) {}
                        if (! this.$store.papan.sematkan) this.$nextTick(() => this.$refs.cariBoard?.focus());
                    },
                    pilihTata(t) {
                        this.tata = t;
                        try { localStorage.setItem('kanban:tata-board', t) } catch (e) {}
                    },
                 }"
                 x-bind:class="$store.papan.sematkan
                    ? 'fixed inset-y-0 left-0 w-[min(18rem,85vw)] rounded-none border-r'
                    : 'absolute bottom-full left-1/2 mb-2 w-[min(28rem,calc(100vw-1.5rem))] -translate-x-1/2 rounded-xl border'"
                 class="flex flex-col overflow-hidden border-line bg-card text-ink shadow-xl">
                <div class="flex items-center gap-2 border-b border-line px-3 py-2.5">
                    <h2 class="text-sm font-semibold">Switch boards</h2>

                    {{-- Tata letak: grid bergambar / daftar --}}
                    <div class="ml-auto flex items-center rounded-md bg-page p-0.5" role="group" aria-label="Board layout">
                        <button type="button" x-on:click="pilihTata('grid')" aria-label="Grid view"
                                x-bind:class="tata === 'grid' ? 'bg-card text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                                class="flex h-7 w-7 items-center justify-center rounded">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 4.5h6v6H4zM14 4.5h6v6h-6zM4 13.5h6v6H4zM14 13.5h6v6h-6z" /></svg>
                        </button>
                        <button type="button" x-on:click="pilihTata('daftar')" aria-label="List view"
                                x-bind:class="tata === 'daftar' ? 'bg-card text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                                class="flex h-7 w-7 items-center justify-center rounded">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 6.5h16M4 12h16M4 17.5h16" /></svg>
                        </button>
                    </div>

                    {{-- Paku panelnya jadi sidebar kiri; pilihannya diingat per browser. --}}
                    <button type="button" x-on:click="$store.papan.togglePaku()"
                            x-bind:aria-pressed="$store.papan.sematkan ? 'true' : 'false'"
                            x-bind:title="$store.papan.sematkan ? 'Unpin sidebar' : 'Pin as sidebar'"
                            aria-label="Pin board list as sidebar"
                            x-bind:class="$store.papan.sematkan ? 'bg-page text-ink' : 'text-ink-muted hover:bg-page'"
                            class="flex h-7 w-7 items-center justify-center rounded-md">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.5h6l-.8 5.2 3.3 3.3H6.5l3.3-3.3zM12 12v8.5" />
                        </svg>
                    </button>

                    <button type="button" wire:click="tutupPanel" aria-label="Close board list"
                            x-show="! $store.papan.sematkan"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-ink-muted hover:bg-page">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>

                <div class="px-3 py-2">
                    <label for="cari-board-apung" class="sr-only">Search board</label>
                    <input id="cari-board-apung" type="search" x-ref="cariBoard" wire:model.live.debounce.300ms="cari"
                           placeholder="Search your boards…"
                           class="block min-h-[38px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                </div>

                <div class="gulir-gelap min-h-0 flex-1 overflow-y-auto px-3 pb-2"
                     x-bind:class="$store.papan.sematkan ? '' : 'max-h-[55vh]'">
                    @forelse ($this->kelompok as $grup)
                        <div wire:key="grup-{{ Str::slug($grup['judul']) }}">
                            <h3 class="pb-1.5 pt-2 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $grup['judul'] }}</h3>

                            {{-- Grid bergambar --}}
                            <div x-show="tata === 'grid'" class="grid gap-2 pb-1"
                                 x-bind:class="$store.papan.sematkan ? 'grid-cols-2' : 'grid-cols-3'">
                                @foreach ($grup['board'] as $b)
                                    <div wire:key="grid-{{ $grup['judul'] }}-{{ $b->id }}" class="group relative">
                                        <a href="{{ route('kanban.board', $b) }}" wire:navigate title="{{ $b->nama }}"
                                           @class(['block overflow-hidden rounded-lg ring-1 ring-line hover:ring-brand', 'ring-2 ring-navy' => $b->id === $boardId])>
                                            <span class="block h-16 w-full bg-cover bg-center {{ $b->latar_path ? '' : Warna::board($b->warna) }}"
                                                  @if ($b->latar_path) style="background-image: url('{{ route('kanban.latar', $b) }}')" @endif></span>
                                            <span class="block truncate px-1.5 py-1 text-[11px] font-medium leading-tight">{{ $b->nama }}</span>
                                        </a>
                                        <x-kanban.bintang-board :board="$b" class="absolute right-1 top-1 h-6 w-6 drop-shadow" />
                                    </div>
                                @endforeach
                            </div>

                            {{-- Daftar ringkas --}}
                            <div x-show="tata === 'daftar'" x-cloak class="-mx-1">
                                @foreach ($grup['board'] as $b)
                                    <div wire:key="daftar-{{ $grup['judul'] }}-{{ $b->id }}"
                                         @class([
                                             'group flex items-center gap-2.5 rounded-lg px-1 text-sm hover:bg-page',
                                             'bg-page font-medium' => $b->id === $boardId,
                                         ])>
                                        <a href="{{ route('kanban.board', $b) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-2.5 py-1.5">
                                            <span class="h-7 w-10 shrink-0 rounded bg-cover bg-center {{ $b->latar_path ? '' : Warna::board($b->warna) }}"
                                                  @if ($b->latar_path) style="background-image: url('{{ route('kanban.latar', $b) }}')" @endif></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $b->nama }}</span>
                                            @if ($b->id === $boardId)
                                                <span class="shrink-0 text-xs text-ink-muted">Currently open</span>
                                            @endif
                                        </a>
                                        <x-kanban.bintang-board :board="$b" class="h-7 w-7 shrink-0" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-ink-muted">No matching board.</p>
                    @endforelse
                </div>

                <a href="{{ route('kanban.beranda') }}" wire:navigate
                   class="block border-t border-line px-3 py-2.5 text-sm text-navy hover:bg-page">See all boards</a>
            </div>
        @endif

        {{-- Bilahnya sendiri --}}
        <div class="flex items-center gap-1 rounded-full bg-navy-900/95 p-1 text-white shadow-lg ring-1 ring-white/15 backdrop-blur">
            @if ($tujuan)
                <a href="{{ route('kanban.board', $tujuan) }}" wire:navigate
                   title="{{ $diBoard ? $tujuan->nama : 'Back ke '.$tujuan->nama }}"
                   @class([
                       'flex h-9 items-center gap-2 rounded-full px-3 text-sm',
                       'bg-white/20 font-medium' => $diBoard,
                       'text-white/85 hover:bg-white/10 hover:text-white' => ! $diBoard,
                   ])>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 5.5h16v13H4z" /><path stroke-linecap="round" d="M10 5.5v13M15 5.5v9" /></svg>
                    Board
                </a>
            @else
                <span class="flex h-9 cursor-default items-center gap-2 rounded-full px-3 text-sm text-white/45"
                      title="No board opened yet">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 5.5h16v13H4z" /><path stroke-linecap="round" d="M10 5.5v13M15 5.5v9" /></svg>
                    Board
                </span>
            @endif

            {{-- Saat panelnya dipaku jadi sidebar, tombol ini tidak perlu: sidebarnya
                 sudah selalu terbuka, dan menekannya malah akan menutup sidebar. --}}
            <button type="button" wire:click="togglePanel" aria-haspopup="menu" aria-expanded="{{ $buka ? 'true' : 'false' }}"
                    x-show="! $store.papan.sematkan"
                    @class([
                        'flex h-9 items-center gap-2 rounded-full px-3 text-sm',
                        'bg-white/20 font-medium' => $buka,
                        'text-white/85 hover:bg-white/10 hover:text-white' => ! $buka,
                    ])>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 6.5h6v11H4zM14 6.5h6v7h-6z" /></svg>
                Switch boards
            </button>
        </div>
    </div>
</div>
