@php
    use App\Support\Kanban\Warna;

    $tujuan = $this->boardTujuan;
    $diBoard = $tujuan && $boardId === $tujuan->id;
@endphp

{{-- Bilah mengambang: selalu ada di bawah layar, di halaman kanban mana pun. --}}
<div class="bilah-apung pointer-events-none fixed inset-x-0 bottom-0 z-30 flex justify-center px-3"
     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
    <div class="pointer-events-auto relative" x-on:keydown.escape.window="$wire.tutupPanel()">

        {{-- Panel pindah board --}}
        @if ($buka)
            <div x-on:click.outside="$wire.tutupPanel()"
                 x-init="$nextTick(() => $refs.cariBoard?.focus())"
                 class="absolute bottom-full left-1/2 mb-2 w-[min(22rem,calc(100vw-1.5rem))] -translate-x-1/2 overflow-hidden rounded-xl border border-line bg-card text-ink shadow-xl">
                <div class="flex items-center gap-2 border-b border-line px-3 py-2.5">
                    <h2 class="text-sm font-semibold">Switch boards</h2>
                    <button type="button" wire:click="tutupPanel" aria-label="Tutup daftar board"
                            class="ml-auto flex h-7 w-7 items-center justify-center rounded-md text-ink-muted hover:bg-page">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>

                <div class="px-3 py-2">
                    <label for="cari-board-apung" class="sr-only">Cari board</label>
                    <input id="cari-board-apung" type="search" x-ref="cariBoard" wire:model.live.debounce.300ms="cari"
                           placeholder="Cari board…"
                           class="block min-h-[38px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                </div>

                <div class="max-h-[50vh] overflow-y-auto pb-2">
                    @forelse ($this->kelompok as $grup)
                        <div wire:key="grup-{{ Str::slug($grup['judul']) }}">
                            <h3 class="px-3 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $grup['judul'] }}</h3>
                            @foreach ($grup['board'] as $b)
                                <a href="{{ route('kanban.board', $b) }}" wire:navigate wire:key="bapung-{{ $grup['judul'] }}-{{ $b->id }}"
                                   @class([
                                       'flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-page',
                                       'bg-page font-medium' => $b->id === $boardId,
                                   ])>
                                    <span class="h-6 w-8 shrink-0 rounded {{ Warna::board($b->warna) }}"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $b->nama }}</span>
                                    @if ($b->id === $boardId)
                                        <span class="shrink-0 text-xs text-ink-muted">Sedang dibuka</span>
                                    @elseif ($b->saya_bintang)
                                        <svg class="h-4 w-4 shrink-0 text-[#E2B203]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @empty
                        <p class="px-3 py-4 text-sm text-ink-muted">Tidak ada board yang cocok.</p>
                    @endforelse
                </div>

                <a href="{{ route('kanban.beranda') }}" wire:navigate
                   class="block border-t border-line px-3 py-2.5 text-sm text-navy hover:bg-page">Lihat semua board</a>
            </div>
        @endif

        {{-- Bilahnya sendiri --}}
        <div class="flex items-center gap-1 rounded-full bg-navy-900/95 p-1 text-white shadow-lg ring-1 ring-white/15 backdrop-blur">
            @if ($tujuan)
                <a href="{{ route('kanban.board', $tujuan) }}" wire:navigate
                   title="{{ $diBoard ? $tujuan->nama : 'Kembali ke '.$tujuan->nama }}"
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
                      title="Belum ada board yang dibuka">
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
