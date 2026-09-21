<div class="h-full overflow-y-auto">
    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-ink">{{ $lihatArsip ? 'Board diarsipkan' : 'Board' }}</h1>
                <p class="text-sm text-ink-muted">Workspace Delapan Mata Air</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="$toggle('lihatArsip')"
                        class="min-h-[40px] rounded-lg px-3 text-sm text-ink-muted hover:bg-line/50 hover:text-ink">
                    {{ $lihatArsip ? '← Kembali ke board aktif' : 'Lihat board diarsipkan' }}
                </button>
                @unless ($lihatArsip)
                    <x-button wire:click="bukaFormBuat">+ Buat board</x-button>
                @endunless
            </div>
        </div>

        @if ($pesan)
            <div class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-line bg-card px-4 py-3 text-sm text-ink" role="status">
                <span>{{ $pesan }}</span>
                <button type="button" wire:click="$set('pesan', null)" class="font-medium text-navy underline">Tutup</button>
            </div>
        @endif

        @forelse ($this->kelompok as $kel)
            <section class="mb-8" wire:key="kel-{{ $loop->index }}">
                <h2 class="mb-3 text-sm font-semibold text-ink">{{ $kel['judul'] }}</h2>
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($kel['board'] as $b)
                        <li wire:key="b-{{ $loop->parent->index }}-{{ $b->id }}"
                            class="group relative h-28 overflow-hidden rounded-xl {{ \App\Support\Kanban\Warna::board($b->warna) }} shadow-sm">
                            <a href="{{ route('kanban.board', $b) }}" wire:navigate
                               class="absolute inset-0 flex flex-col justify-between p-3 text-white hover:bg-black/10">
                                <span class="line-clamp-2 pr-8 text-base font-semibold leading-snug">{{ $b->nama }}</span>
                                <span class="flex flex-wrap items-center gap-1.5 text-xs text-white/90">
                                    @if ($b->isOrder())<span class="rounded bg-white/20 px-1.5 py-0.5 font-medium">Otomatis dari order</span>@endif
                                    @if ($b->visibilitas === 'privat')<span class="rounded bg-white/20 px-1.5 py-0.5">Hanya anggota</span>@endif
                                    <span>{{ $b->kartu_count }} kartu</span>
                                </span>
                            </a>
                            @if ($lihatArsip)
                                @if ($b->saya_kelola)
                                    <div class="absolute right-2 top-2 flex gap-1">
                                        <button type="button" wire:click="pulihkan({{ $b->id }})"
                                                class="rounded-md bg-white/90 px-2 py-1 text-xs font-medium text-ink hover:bg-white">Pulihkan</button>
                                        @unless ($b->isOrder())
                                            <button type="button" wire:click="hapus({{ $b->id }})"
                                                    wire:confirm="Hapus board &quot;{{ $b->nama }}&quot; selamanya beserta semua list, kartu, komentar, dan lampirannya? Tindakan ini tidak bisa dibatalkan."
                                                    class="rounded-md bg-[#C9372C] px-2 py-1 text-xs font-medium text-white hover:bg-[#AE2E24]">Hapus</button>
                                        @endunless
                                    </div>
                                @endif
                            @else
                                <button type="button" wire:click="bintang({{ $b->id }})"
                                        aria-label="{{ $b->saya_bintang ? 'Hapus bintang' : 'Beri bintang' }} {{ $b->nama }}"
                                        aria-pressed="{{ $b->saya_bintang ? 'true' : 'false' }}"
                                        @class([
                                            'absolute right-1.5 top-1.5 flex h-9 w-9 items-center justify-center rounded-md hover:bg-black/15',
                                            'text-[#F5CD47]' => $b->saya_bintang,
                                            'text-white/80 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100' => ! $b->saya_bintang,
                                        ])>
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $b->saya_bintang ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
                                </button>
                            @endif
                        </li>
                    @endforeach

                    @if ($loop->last && ! $lihatArsip)
                        <li>
                            <button type="button" wire:click="bukaFormBuat"
                                    class="flex h-28 w-full items-center justify-center rounded-xl border border-dashed border-line bg-card text-sm font-medium text-ink-muted hover:border-brand/50 hover:text-ink">
                                + Buat board baru
                            </button>
                        </li>
                    @endif
                </ul>
            </section>
        @empty
            <p class="rounded-xl border border-line bg-card px-4 py-10 text-center text-sm text-ink-muted">
                {{ $lihatArsip ? 'Tidak ada board yang diarsipkan.' : 'Belum ada board.' }}
            </p>
        @endforelse
    </div>

    {{-- Buat board --}}
    @if ($bukaBuat)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="buat-board"
             x-data x-on:keydown.escape.window="$wire.set('bukaBuat', false)">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('bukaBuat', false)"></div>
            <form wire:submit="buat" role="dialog" aria-modal="true" aria-labelledby="judul-buat-board"
                  class="relative w-full max-w-sm rounded-t-2xl border border-line bg-card p-5 shadow-lg sm:rounded-2xl">
                <h2 id="judul-buat-board" class="text-base font-semibold text-ink">Buat board</h2>

                <div class="mt-4 flex h-24 items-center justify-center rounded-xl {{ \App\Support\Kanban\Warna::board($warna) }}">
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span class="h-12 w-10 rounded bg-white/80"></span>
                        <span class="h-16 w-10 rounded bg-white/80"></span>
                        <span class="h-9 w-10 rounded bg-white/80"></span>
                    </div>
                </div>

                <fieldset class="mt-4">
                    <legend class="text-sm font-medium text-ink">Warna latar</legend>
                    <div class="mt-2 grid grid-cols-9 gap-1.5">
                        @foreach (\App\Support\Kanban\Warna::BOARD as $k => [$labelWarna, $kelas])
                            <label class="relative">
                                <input type="radio" wire:model.live="warna" value="{{ $k }}" class="peer sr-only">
                                <span title="{{ $labelWarna }}"
                                      class="block h-8 cursor-pointer rounded-md {{ $kelas }} ring-offset-2 peer-checked:ring-2 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand"></span>
                                <span class="sr-only">{{ $labelWarna }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-4">
                    <x-input label="Nama board" wire:model="nama" :error="$errors->first('nama')" placeholder="mis. 5. Editing" autofocus />
                </div>

                <div class="mt-4 space-y-1.5">
                    <label for="visibilitas-board" class="block text-sm font-medium text-ink">Siapa yang bisa melihat</label>
                    <select id="visibilitas-board" wire:model="visibilitas"
                            class="block min-h-[44px] w-full rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
                        @foreach (\App\Models\Kanban\Board::VISIBILITAS as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button type="button" variant="ghost" wire:click="$set('bukaBuat', false)">Batal</x-button>
                    <x-button type="submit">
                        <span wire:loading.remove wire:target="buat">Buat</span>
                        <span wire:loading wire:target="buat">Membuat…</span>
                    </x-button>
                </div>
            </form>
        </div>
    @endif
</div>
