@php use App\Support\Kanban\Warna; @endphp

<div class="h-full overflow-y-auto pb-16">
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
        <h1 class="text-xl font-semibold text-ink">My cards</h1>
        <p class="text-sm text-ink-muted">Search cards across every board you can see.</p>

        {{-- Tab --}}
        <div class="mt-4 flex flex-wrap gap-1 border-b border-line" role="tablist">
            @foreach (\App\Livewire\Kanban\KartuSaya::TAB as $kunci => $labelTab)
                <button type="button" role="tab" aria-selected="{{ $tab === $kunci ? 'true' : 'false' }}"
                        wire:click="gantiTab('{{ $kunci }}')"
                        @class([
                            'min-h-[40px] rounded-t-lg px-3 text-sm font-medium',
                            'border-b-2 border-brand text-ink' => $tab === $kunci,
                            'text-ink-muted hover:text-ink' => $tab !== $kunci,
                        ])>{{ $labelTab }}</button>
            @endforeach
        </div>

        {{-- Saringan --}}
        <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_12rem]">
            <div>
                <label for="cari-kartu-saya" class="sr-only">Search cards</label>
                <input id="cari-kartu-saya" type="search" wire:model.live.debounce.400ms="cari"
                       placeholder="Search title, description, or #123…"
                       class="block min-h-[42px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
            </div>
            <div>
                <label for="saring-board" class="sr-only">Board</label>
                <select id="saring-board" wire:model.live="boardId"
                        class="block min-h-[42px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                    <option value="">All boards</option>
                    @foreach ($this->boardTerlihat as $b)
                        <option value="{{ $b->id }}">{{ $b->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="saring-tenggat-saya" class="sr-only">Due date</label>
                <select id="saring-tenggat-saya" wire:model.live="saringTenggat"
                        class="block min-h-[42px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                    <option value="">All due dates</option>
                    <option value="lewat">Overdue</option>
                    <option value="minggu">Due this week</option>
                    <option value="tanpa">No due date</option>
                </select>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
            <label class="flex min-h-[32px] items-center gap-2 text-ink-muted">
                <input type="checkbox" wire:model.live="termasukArsip" class="rounded text-brand focus:ring-brand/30">
                Include archived
            </label>
            <button type="button" wire:click="bersihkan" class="text-navy underline">Clear filter</button>
            <span class="ml-auto text-ink-muted">{{ $this->hasil->total() }} kartu</span>
        </div>

        {{-- Hasil --}}
        <ul class="mt-4 space-y-2">
            @forelse ($this->hasil as $kartu)
                @php $tenggat = $kartu->keadaanTenggat(); @endphp
                <li wire:key="ks-{{ $kartu->id }}" class="rounded-xl border border-line bg-card">
                    <a href="{{ route('kanban.board', $kartu->board_id) }}?kartu={{ $kartu->id }}" wire:navigate
                       class="flex gap-3 px-4 py-3 hover:bg-page">
                        <span class="mt-1 h-8 w-1.5 shrink-0 rounded-full {{ Warna::board($kartu->board?->warna) }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block break-words font-medium text-ink">
                                <span class="font-mono text-xs text-ink-muted">#{{ $kartu->id }}</span> {{ $kartu->judul }}
                            </span>
                            <span class="mt-1 flex flex-wrap items-center gap-1 text-xs text-ink-muted">
                                <span class="inline-flex items-center gap-1 rounded bg-page px-1.5 py-0.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ Warna::board($kartu->board?->warna) }}"></span>
                                    <span class="font-medium text-ink">{{ $kartu->board?->nama }}</span>
                                </span>
                                <span aria-hidden="true">›</span>
                                <span class="rounded bg-page px-1.5 py-0.5 font-medium text-ink">{{ $kartu->kolom?->nama }}</span>
                                @if ($kartu->diarsipkan_at)<span class="rounded bg-[#FFECEB] px-1.5 py-0.5 text-[#AE2E24]">diarsipkan</span>@endif
                            </span>
                            @if ($kartu->label->isNotEmpty() || $tenggat || $kartu->anggota->isNotEmpty())
                                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @foreach ($kartu->label as $l)
                                        <span class="inline-block h-5 rounded px-1.5 text-[11px] font-medium leading-5 {{ Warna::label($l->warna) }}">{{ $l->nama ?: Warna::namaLabel($l->warna) }}</span>
                                    @endforeach
                                    @if ($tenggat)
                                        <span @class([
                                            'inline-flex items-center rounded px-1.5 py-0.5 text-[11px]',
                                            'bg-[#1F845A] text-white' => $tenggat === 'selesai',
                                            'bg-[#C9372C] text-white' => $tenggat === 'lewat',
                                            'bg-[#F5CD47] text-ink' => $tenggat === 'segera',
                                            'bg-[#E9EBEE] text-ink' => $tenggat === 'biasa',
                                        ])>{{ $kartu->tenggat_pada->translatedFormat('j M Y') }}</span>
                                    @endif
                                    @foreach ($kartu->anggota as $a)
                                        <span title="{{ $a->nama ?? $a->name }}"><x-avatar :name="$a->nama ?? $a->name" size="sm" class="!h-6 !w-6 !text-[10px]" /></span>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-line bg-card px-4 py-10 text-center text-sm text-ink-muted">
                    @if ($tab === 'saya')
                        No cards assigned to you yet.
                    @elseif ($tab === 'ikuti')
                        You are not following any card yet.
                    @else
                        No matching cards.
                    @endif
                </li>
            @endforelse
        </ul>

        <div class="mt-4">{{ $this->hasil->links() }}</div>
    </div>
</div>
