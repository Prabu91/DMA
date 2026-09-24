@php
    use App\Support\Kanban\Warna;

    $tujuan = $this->boardTujuan;
    $diBoard = $tujuan && $boardId === $tujuan->id;
@endphp

{{-- Bilah mengambang: selalu ada di bawah layar, di halaman kanban mana pun. --}}
<div class="bilah-apung pointer-events-none fixed inset-x-0 bottom-0 z-30 flex justify-center px-3"
     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
    <div class="pointer-events-auto relative" x-on:keydown.escape.window="$wire.tutupPanel()">

        {{-- Panel pindah board: grid bergambar atau daftar ringkas --}}
        @if ($buka)
            <div x-on:click.outside="$wire.tutupPanel()"
                 x-data="{
                    tata: 'grid',
                    init() {
                        try { this.tata = localStorage.getItem('kanban:tata-board') ?? 'grid' } catch (e) {}
                        this.$nextTick(() => this.$refs.cariBoard?.focus());
                    },
                    pilihTata(t) {
                        this.tata = t;
                        try { localStorage.setItem('kanban:tata-board', t) } catch (e) {}
                    },
                 }"
                 class="absolute bottom-full left-1/2 mb-2 w-[min(28rem,calc(100vw-1.5rem))] -translate-x-1/2 overflow-hidden rounded-xl border border-line bg-card text-ink shadow-xl">
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

                    <button type="button" wire:click="tutupPanel" aria-label="Close board list"
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

                <div class="gulir-gelap max-h-[55vh] overflow-y-auto px-3 pb-2">
                    @forelse ($this->kelompok as $grup)
                        <div wire:key="grup-{{ Str::slug($grup['judul']) }}">
                            <h3 class="pb-1.5 pt-2 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $grup['judul'] }}</h3>

                            {{-- Grid bergambar --}}
                            <div x-show="tata === 'grid'" class="grid grid-cols-3 gap-2 pb-1">
                                @foreach ($grup['board'] as $b)
                                    <a href="{{ route('kanban.board', $b) }}" wire:navigate wire:key="grid-{{ $grup['judul'] }}-{{ $b->id }}"
                                       title="{{ $b->nama }}"
                                       @class(['group overflow-hidden rounded-lg ring-1 ring-line hover:ring-brand', 'ring-2 ring-navy' => $b->id === $boardId])>
                                        <span class="relative block h-16 w-full bg-cover bg-center {{ $b->latar_path ? '' : Warna::board($b->warna) }}"
                                              @if ($b->latar_path) style="background-image: url('{{ route('kanban.latar', $b) }}')" @endif>
                                            @if ($b->saya_bintang)
                                                <svg class="absolute right-1 top-1 h-3.5 w-3.5 text-[#E2B203] drop-shadow" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
                                            @endif
                                        </span>
                                        <span class="block truncate px-1.5 py-1 text-[11px] font-medium leading-tight">{{ $b->nama }}</span>
                                    </a>
                                @endforeach
                            </div>

                            {{-- Daftar ringkas --}}
                            <div x-show="tata === 'daftar'" x-cloak class="-mx-1">
                                @foreach ($grup['board'] as $b)
                                    <a href="{{ route('kanban.board', $b) }}" wire:navigate wire:key="daftar-{{ $grup['judul'] }}-{{ $b->id }}"
                                       @class([
                                           'flex items-center gap-2.5 rounded-lg px-1 py-1.5 text-sm hover:bg-page',
                                           'bg-page font-medium' => $b->id === $boardId,
                                       ])>
                                        <span class="h-7 w-10 shrink-0 rounded bg-cover bg-center {{ $b->latar_path ? '' : Warna::board($b->warna) }}"
                                              @if ($b->latar_path) style="background-image: url('{{ route('kanban.latar', $b) }}')" @endif></span>
                                        <span class="min-w-0 flex-1 truncate">{{ $b->nama }}</span>
                                        @if ($b->id === $boardId)
                                            <span class="shrink-0 text-xs text-ink-muted">Currently open</span>
                                        @elseif ($b->saya_bintang)
                                            <svg class="h-4 w-4 shrink-0 text-[#E2B203]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
                                        @endif
                                    </a>
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

            <button type="button" wire:click="togglePanel" aria-haspopup="menu" aria-expanded="{{ $buka ? 'true' : 'false' }}"
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
