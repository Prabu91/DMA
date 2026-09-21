<div class="relative" x-data="{ buka: false }" wire:poll.30s>
    <button type="button" x-on:click="buka = ! buka" x-on:keydown.escape.window="buka = false"
            :aria-expanded="buka" aria-label="Notifikasi{{ $this->belumDibaca ? ' ('.$this->belumDibaca.' belum dibaca)' : '' }}"
            class="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/15">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.9a2 2 0 01-.6-1.4V10a6 6 0 10-12 0v3.7c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
        </svg>
        @if ($this->belumDibaca)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[#C9372C] px-1 text-[10px] font-semibold text-white">
                {{ $this->belumDibaca > 9 ? '9+' : $this->belumDibaca }}
            </span>
        @endif
    </button>

    <div x-show="buka" x-cloak x-on:click.outside="buka = false"
         class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-line bg-card text-ink shadow-lg">
        <div class="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
            <h2 class="text-sm font-semibold">Notifikasi</h2>
            <div class="flex items-center gap-2 text-xs">
                <label class="flex items-center gap-1.5 text-ink-muted">
                    <input type="checkbox" wire:model.live="hanyaBelumDibaca" class="h-3.5 w-3.5 rounded text-brand focus:ring-brand/30">
                    Belum dibaca
                </label>
                @if ($this->belumDibaca)
                    <button type="button" wire:click="bacaSemua" class="text-navy underline">Tandai dibaca</button>
                @endif
            </div>
        </div>

        <ul class="max-h-[60vh] divide-y divide-line overflow-y-auto">
            @forelse ($this->kabar as $n)
                @php $d = $n->data; @endphp
                <li wire:key="kabar-{{ $n->id }}" @class(['flex gap-2 px-3 py-2.5', 'bg-brand/5' => ! $n->read_at])>
                    <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-brand' => ! $n->read_at, 'bg-transparent' => $n->read_at])></span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('kanban.board', $d['board_id'] ?? 0) }}?kartu={{ $d['kartu_id'] ?? '' }}" wire:navigate
                           wire:click="baca('{{ $n->id }}')" x-on:click="buka = false"
                           class="block text-sm hover:underline">
                            <span class="font-medium">{{ $d['kalimat'] ?? 'Ada perubahan' }}</span>
                            di <span class="font-medium">{{ $d['kartu'] ?? 'kartu' }}</span>
                        </a>
                        @if (! empty($d['cuplikan']))
                            <p class="mt-0.5 line-clamp-2 text-xs text-ink-muted">{{ $d['cuplikan'] }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-ink-muted">
                            {{ $d['board'] ?? '' }} · {{ $n->created_at?->diffForHumans() }}
                            @if (! $n->read_at)
                                · <button type="button" wire:click="baca('{{ $n->id }}')" class="underline">Tandai dibaca</button>
                            @endif
                        </p>
                    </div>
                </li>
            @empty
                <li class="px-3 py-8 text-center text-sm text-ink-muted">
                    {{ $hanyaBelumDibaca ? 'Semua notifikasi sudah dibaca.' : 'Belum ada notifikasi.' }}
                </li>
            @endforelse
        </ul>
    </div>
</div>
