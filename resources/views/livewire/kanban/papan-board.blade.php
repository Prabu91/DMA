@php
    use App\Support\Kanban\Warna;
    $ubah = $this->bolehUbah;
    $kelola = $this->bolehKelola;
    $ikon = [
        'tenggat' => 'M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z',
        'deskripsi' => 'M4 6h16M4 12h16M4 18h10',
        'komentar' => 'M8 10h8M8 14h5m-9 6l2.3-3.1A8 8 0 1121 12a8 8 0 01-11.7 7.1L4 20z',
        'lampiran' => 'M15.2 7.5l-6.4 6.4a2 2 0 002.8 2.8l6.7-6.7a4 4 0 00-5.7-5.7L5.9 11a6 6 0 008.5 8.5l6.1-6.1',
        'checklist' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
@endphp

<div class="flex h-full flex-col {{ Warna::board($board->warna) }}"
     x-data="{
        menu: false,
        saring: false,
        pewaktu: null,
        init() {
            // Segarkan papan berkala supaya perubahan rekan ikut tampil.
            this.pewaktu = setInterval(() => {
                const aktif = document.activeElement?.tagName;
                if (document.hidden || document.body.classList.contains('sorting')) return;
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(aktif)) return;
                if (this.menu || $wire.kartuId) return;
                $wire.$refresh();
            }, 15000);
        },
        destroy() { clearInterval(this.pewaktu); },
     }">

    {{-- Kepala board --}}
    <div class="flex shrink-0 flex-wrap items-center gap-1.5 bg-black/25 px-3 py-2 text-white sm:gap-2 sm:px-4">
        @if ($kelola)
            <form wire:submit="simpanNamaBoard" class="min-w-0">
                <label for="nama-board" class="sr-only">Nama board</label>
                <input id="nama-board" type="text" wire:model="namaBoard" value="{{ $namaBoard }}" x-on:blur="$wire.simpanNamaBoard()"
                       x-on:keydown.enter.prevent="$el.blur()"
                       class="w-44 rounded-md border-0 bg-transparent px-2 py-1 text-lg font-semibold text-white hover:bg-white/15 focus:bg-white focus:text-ink focus:ring-2 focus:ring-brand sm:w-auto sm:min-w-[12rem]"
                       style="field-sizing: content">
            </form>
        @else
            <h1 class="truncate px-2 py-1 text-lg font-semibold">{{ $board->nama }}</h1>
        @endif

        <button type="button" wire:click="bintang"
                aria-label="{{ $this->sayaBintang ? 'Hapus bintang' : 'Beri bintang' }}" aria-pressed="{{ $this->sayaBintang ? 'true' : 'false' }}"
                class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/15 {{ $this->sayaBintang ? 'text-[#F5CD47]' : '' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $this->sayaBintang ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
        </button>

        <span class="hidden rounded-md bg-white/15 px-2 py-1 text-xs sm:inline">
            {{ $board->isOrder() ? 'Otomatis dari order' : \App\Models\Kanban\Board::VISIBILITAS[$board->visibilitas] ?? '' }}
        </span>

        @if ($board->diarsipkan_at)
            <span class="rounded-md bg-[#F5CD47] px-2 py-1 text-xs font-medium text-ink">Board diarsipkan — hanya baca</span>
        @endif

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            <div class="hidden -space-x-1.5 md:flex" aria-label="Anggota board">
                @foreach ($this->anggotaBoard->take(6) as $a)
                    <span title="{{ $a->nama ?? $a->name }}" wire:key="ang-{{ $a->id }}">
                        <x-avatar :name="$a->nama ?? $a->name" size="sm" class="!bg-white !text-navy ring-2 ring-black/20" />
                    </span>
                @endforeach
                @if ($this->anggotaBoard->count() > 6)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/25 text-xs ring-2 ring-black/20">+{{ $this->anggotaBoard->count() - 6 }}</span>
                @endif
            </div>

            @if (! $board->isOrder() && ! $this->sayaAnggota && ! $board->diarsipkan_at)
                <button type="button" wire:click="gabung" class="h-9 rounded-md bg-white px-3 text-sm font-medium text-ink hover:bg-white/90">Gabung board</button>
            @endif

            {{-- Saring --}}
            <div class="relative">
                <button type="button" x-on:click="saring = ! saring" :aria-expanded="saring"
                        class="flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm hover:bg-white/15 {{ $this->adaSaringan ? 'bg-white text-ink hover:bg-white/90' : '' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M3 5h18M6 12h12M10 19h4" /></svg>
                    <span class="hidden sm:inline">Saring</span>
                </button>
                <div x-show="saring" x-cloak x-transition.opacity x-on:click.outside="saring = false" x-on:keydown.escape.window="saring = false"
                     class="absolute right-0 z-40 mt-2 max-h-[70vh] w-[min(20rem,calc(100vw-1.5rem))] overflow-y-auto rounded-xl border border-line bg-card p-4 text-sm text-ink shadow-lg">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Saring kartu</h2>
                        @if ($this->adaSaringan)
                            <button type="button" wire:click="bersihkanSaringan" class="text-xs text-navy underline">Bersihkan</button>
                        @endif
                    </div>
                    <label for="cari-kartu" class="mt-3 block text-xs font-medium text-ink-muted">Kata kunci</label>
                    <input id="cari-kartu" type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari judul kartu…"
                           class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">

                    <fieldset class="mt-4">
                        <legend class="text-xs font-medium text-ink-muted">Tenggat</legend>
                        @foreach (['' => 'Semua', 'lewat' => 'Lewat tenggat', 'segera' => 'Tenggat 24 jam ke depan', 'selesai' => 'Ditandai selesai', 'tanpa' => 'Tanpa tenggat'] as $k => $t)
                            <label class="mt-1 flex min-h-[32px] items-center gap-2">
                                <input type="radio" wire:model.live="saringTenggat" value="{{ $k }}" class="text-brand focus:ring-brand/30"> {{ $t }}
                            </label>
                        @endforeach
                    </fieldset>

                    @if ($this->labelBoard->isNotEmpty())
                        <fieldset class="mt-4">
                            <legend class="text-xs font-medium text-ink-muted">Label</legend>
                            @foreach ($this->labelBoard as $l)
                                <label class="mt-1 flex min-h-[32px] items-center gap-2" wire:key="sl-{{ $l->id }}">
                                    <input type="checkbox" wire:model.live="saringLabel" value="{{ $l->id }}" class="rounded text-brand focus:ring-brand/30">
                                    <span class="h-6 flex-1 truncate rounded px-2 text-xs font-medium leading-6 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif

                    @if ($this->anggotaBoard->isNotEmpty())
                        <fieldset class="mt-4">
                            <legend class="text-xs font-medium text-ink-muted">Anggota kartu</legend>
                            @foreach ($this->anggotaBoard as $a)
                                <label class="mt-1 flex min-h-[32px] items-center gap-2" wire:key="sa-{{ $a->id }}">
                                    <input type="checkbox" wire:model.live="saringAnggota" value="{{ $a->id }}" class="rounded text-brand focus:ring-brand/30">
                                    {{ $a->nama ?? $a->name }}
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                </div>
            </div>

            <button type="button" x-on:click="menu = true" aria-label="Menu board"
                    class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/15">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
            </button>
        </div>
    </div>

    @if ($pesan)
        <div class="mx-3 mt-2 flex items-center justify-between gap-3 rounded-lg bg-[#F5CD47] px-3 py-2 text-sm text-ink sm:mx-4" role="status">
            <span>{{ $pesan }}</span>
            <button type="button" wire:click="$set('pesan', null)" class="font-medium underline">Tutup</button>
        </div>
    @endif

    {{-- List & kartu --}}
    <div class="min-h-0 flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex h-full items-start gap-3 p-3 sm:px-4">
            <ol @if ($ubah) wire:sort="urutKolom" wire:sort:config="{ handle: '.pegangan-list' }" @endif
                class="flex h-full items-start gap-3" aria-label="List">
                @foreach ($this->kolom as $kolom)
                    <li wire:key="kolom-{{ $kolom->id }}" wire:sort:item="{{ $kolom->id }}"
                        class="flex max-h-full w-[272px] shrink-0 flex-col rounded-xl bg-[#F1F2F4] text-ink shadow-sm">
                        <div class="flex items-start gap-1 px-2 pt-2"
                             x-data="{ ubah: false, nama: @js($kolom->nama), simpan() { this.ubah = false; if (this.nama.trim() && this.nama !== @js($kolom->nama)) $wire.ubahNamaKolom({{ $kolom->id }}, this.nama) } }">
                            <div @class(['pegangan-list min-w-0 flex-1 rounded-md', 'cursor-grab active:cursor-grabbing' => $ubah]) @if ($ubah) wire:sort:handle @endif>
                                <h2 x-show="! ubah" @if ($ubah) x-on:click="ubah = true; $nextTick(() => $refs.masukan.select())" @endif
                                    class="break-words px-2 py-1.5 text-sm font-semibold">{{ $kolom->nama }}</h2>
                                @if ($ubah)
                                    <input x-show="ubah" x-cloak x-ref="masukan" type="text" x-model="nama" wire:sort:ignore aria-label="Nama list"
                                           x-on:keydown.enter.prevent="simpan()" x-on:keydown.escape="ubah = false; nama = @js($kolom->nama)" x-on:blur="simpan()"
                                           class="block w-full rounded-md border-brand px-2 py-1 text-sm font-semibold focus:ring-brand/30">
                                @endif
                            </div>
                            <span class="mt-1.5 shrink-0 rounded px-1.5 text-xs text-ink-muted">{{ $kolom->kartu->count() }}</span>
                            @if ($ubah)
                                <div class="relative shrink-0" x-data="{ buka: false }">
                                    <button type="button" x-on:click="buka = ! buka" aria-label="Menu list {{ $kolom->nama }}"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-line hover:text-ink">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
                                    </button>
                                    <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.window="buka = false"
                                         class="absolute left-0 z-30 mt-1 w-52 rounded-xl border border-line bg-card py-1 text-sm shadow-lg">
                                        <button type="button" x-on:click="buka = false" wire:click="mulaiTambahKartu({{ $kolom->id }})" class="block w-full px-3 py-2 text-left hover:bg-page">Tambah kartu</button>
                                        <button type="button" x-on:click="buka = false; ubah = true; $nextTick(() => $refs.masukan.select())" class="block w-full px-3 py-2 text-left hover:bg-page">Ubah nama list</button>
                                        <button type="button" x-on:click="buka = false" wire:click="arsipkanKolom({{ $kolom->id }})"
                                                wire:confirm="Arsipkan list &quot;{{ $kolom->nama }}&quot; beserta kartunya? List bisa dipulihkan dari menu board."
                                                class="block w-full px-3 py-2 text-left text-[#AE2E24] hover:bg-page">Arsipkan list</button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <ol @if ($ubah) wire:sort="urutKartu" wire:sort:group="kartu" wire:sort:group-id="{{ $kolom->id }}" @endif
                            class="flex min-h-[10px] flex-col gap-2 overflow-y-auto px-2 py-1" aria-label="Kartu di {{ $kolom->nama }}">
                            @foreach ($kolom->kartu as $kartu)
                                @php $tenggat = $kartu->keadaanTenggat(); @endphp
                                <li wire:key="kartu-{{ $kartu->id }}" wire:sort:item="{{ $kartu->id }}"
                                    class="group rounded-lg bg-card shadow-[0_1px_1px_rgba(9,30,66,.25)] hover:ring-2 hover:ring-brand/60">
                                    <button type="button" wire:click="bukaKartu({{ $kartu->id }})"
                                            class="block w-full overflow-hidden rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                                        @if ($kartu->coverLampiran?->isGambar())
                                            <img src="{{ route('kanban.lampiran', $kartu->coverLampiran) }}" alt="" loading="lazy" class="max-h-40 w-full object-cover">
                                        @elseif ($kartu->cover_warna)
                                            <span class="block h-8 {{ Warna::labelLatar($kartu->cover_warna) }}"></span>
                                        @endif
                                        <span class="block px-3 pb-2 pt-2">
                                            @if ($kartu->label->isNotEmpty())
                                                <span class="mb-1.5 flex flex-wrap gap-1">
                                                    @foreach ($kartu->label as $l)
                                                        <span class="inline-block h-4 min-w-[2.5rem] max-w-full truncate rounded px-1.5 text-[11px] font-medium leading-4 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                                    @endforeach
                                                </span>
                                            @endif
                                            <span class="block break-words text-sm text-ink">{{ $kartu->judul }}</span>

                                            @php
                                                $adaLencana = $tenggat || $kartu->deskripsi || $kartu->komentar_count || $kartu->lampiran_count || $kartu->checklist_item_count || $kartu->order_id;
                                            @endphp
                                            @if ($adaLencana || $kartu->anggota->isNotEmpty())
                                                <span class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-muted">
                                                    @if ($kartu->order_id)
                                                        <span class="rounded bg-navy/10 px-1.5 py-0.5 font-medium text-navy">{{ $kartu->order?->isSusulan() ? 'Susulan' : 'Order' }}</span>
                                                    @endif
                                                    @if ($tenggat)
                                                        <span @class([
                                                            'inline-flex items-center gap-1 rounded px-1.5 py-0.5',
                                                            'bg-[#1F845A] text-white' => $tenggat === 'selesai',
                                                            'bg-[#C9372C] text-white' => $tenggat === 'lewat',
                                                            'bg-[#F5CD47] text-ink' => $tenggat === 'segera',
                                                        ]) title="Tenggat">
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['tenggat'] }}" /></svg>
                                                            {{ $kartu->tenggat_pada->translatedFormat('j M') }}
                                                        </span>
                                                    @endif
                                                    @if ($kartu->deskripsi)
                                                        <span title="Kartu ini punya deskripsi"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['deskripsi'] }}" /></svg><span class="sr-only">Ada deskripsi</span></span>
                                                    @endif
                                                    @if ($kartu->komentar_count)
                                                        <span class="inline-flex items-center gap-0.5" title="Komentar"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon['komentar'] }}" /></svg>{{ $kartu->komentar_count }}</span>
                                                    @endif
                                                    @if ($kartu->lampiran_count)
                                                        <span class="inline-flex items-center gap-0.5" title="Lampiran"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['lampiran'] }}" /></svg>{{ $kartu->lampiran_count }}</span>
                                                    @endif
                                                    @if ($kartu->checklist_item_count)
                                                        @php $beres = $kartu->checklist_selesai_count === $kartu->checklist_item_count; @endphp
                                                        <span @class(['inline-flex items-center gap-0.5 rounded px-1 py-0.5', 'bg-[#1F845A] text-white' => $beres]) title="Checklist">
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon['checklist'] }}" /></svg>{{ $kartu->checklist_selesai_count }}/{{ $kartu->checklist_item_count }}
                                                        </span>
                                                    @endif
                                                    @if ($kartu->anggota->isNotEmpty())
                                                        <span class="ml-auto flex -space-x-1">
                                                            @foreach ($kartu->anggota->take(3) as $a)
                                                                <span title="{{ $a->nama ?? $a->name }}"><x-avatar :name="$a->nama ?? $a->name" size="sm" class="!h-6 !w-6 !text-[10px] ring-2 ring-white" /></span>
                                                            @endforeach
                                                        </span>
                                                    @endif
                                                </span>
                                            @endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ol>

                        @if ($ubah)
                            @if ($tambahKartuDi === $kolom->id)
                                <form wire:submit="tambahKartu" class="px-2 pb-2 pt-1" x-data x-init="$nextTick(() => $refs.judul.focus())">
                                    <label for="judul-kartu-{{ $kolom->id }}" class="sr-only">Judul kartu</label>
                                    <textarea id="judul-kartu-{{ $kolom->id }}" x-ref="judul" wire:model="judulKartuBaru" rows="2"
                                              placeholder="Masukkan judul kartu…"
                                              x-on:keydown.enter.prevent="$wire.tambahKartu().then(() => $refs.judul?.focus())"
                                              x-on:keydown.escape="$wire.batalTambahKartu()"
                                              class="block w-full resize-none rounded-lg border-0 text-sm shadow-[0_1px_1px_rgba(9,30,66,.25)] focus:ring-2 focus:ring-brand"></textarea>
                                    @error('judulKartuBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                    <div class="mt-2 flex items-center gap-1">
                                        <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Tambah kartu</button>
                                        <button type="button" wire:click="batalTambahKartu" aria-label="Batal" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-line">✕</button>
                                    </div>
                                </form>
                            @else
                                <button type="button" wire:click="mulaiTambahKartu({{ $kolom->id }})"
                                        class="mx-2 mb-2 mt-1 flex min-h-[36px] items-center gap-2 rounded-lg px-2 text-left text-sm text-ink-muted hover:bg-line/70 hover:text-ink">
                                    <span aria-hidden="true" class="text-lg leading-none">+</span> Tambah kartu
                                </button>
                            @endif
                        @else
                            <div class="h-2"></div>
                        @endif
                    </li>
                @endforeach
            </ol>

            @if ($ubah)
                <div class="w-[272px] shrink-0" x-data="{ buka: false }">
                    <button type="button" x-show="! buka" x-on:click="buka = true; $nextTick(() => $refs.namaList.focus())"
                            class="flex min-h-[44px] w-full items-center gap-2 rounded-xl bg-white/25 px-3 text-left text-sm font-medium text-white hover:bg-white/35">
                        <span aria-hidden="true" class="text-lg leading-none">+</span> {{ $this->kolom->isEmpty() ? 'Tambah list' : 'Tambah list lain' }}
                    </button>
                    <form x-show="buka" x-cloak wire:submit="tambahKolom" x-on:keydown.escape="buka = false"
                          class="rounded-xl bg-[#F1F2F4] p-2 shadow-sm">
                        <label for="nama-list-baru" class="sr-only">Nama list</label>
                        <input id="nama-list-baru" x-ref="namaList" type="text" wire:model="namaKolomBaru" placeholder="Masukkan nama list…"
                               class="block min-h-[36px] w-full rounded-md border-brand text-sm focus:ring-brand/30">
                        @error('namaKolomBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                        <div class="mt-2 flex items-center gap-1">
                            <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Tambah list</button>
                            <button type="button" x-on:click="buka = false" aria-label="Batal" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-line">✕</button>
                        </div>
                    </form>
                </div>
            @elseif ($this->kolom->isEmpty())
                <p class="rounded-xl bg-white/90 px-4 py-3 text-sm text-ink">Board ini belum punya list.</p>
            @endif
        </div>
    </div>

    {{-- Menu board (panel samping) --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="menu = false">
        <div class="absolute inset-0 bg-ink/30" x-on:click="menu = false"></div>
        <aside x-show="menu" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full"
               x-data="{ bagian: 'utama' }" role="dialog" aria-modal="true" aria-label="Menu board"
               class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-card text-sm text-ink shadow-xl">
            <div class="flex h-12 shrink-0 items-center gap-2 border-b border-line px-2">
                <button type="button" x-show="bagian !== 'utama'" x-on:click="bagian = 'utama'" aria-label="Kembali"
                        class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-page">‹</button>
                <h2 class="flex-1 text-center font-semibold"
                    x-text="({ utama: 'Menu', warna: 'Ganti latar', label: 'Label', anggota: 'Anggota', arsip: 'Item diarsipkan', aktivitas: 'Aktivitas' })[bagian]">Menu</h2>
                <button type="button" x-on:click="menu = false" aria-label="Tutup menu" class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-page">✕</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                {{-- Utama --}}
                <div x-show="bagian === 'utama'" class="space-y-1">
                    @if ($board->isOrder())
                        <p class="mb-3 rounded-lg bg-page px-3 py-2 text-xs text-ink-muted">Kartu di board ini dibuat otomatis dari order dan masuk ke list marketing masing-masing. Semua staf bisa memindahkannya.</p>
                    @endif
                    @if ($kelola && ! $board->isOrder())
                        <div class="mb-2 rounded-lg bg-page px-3 py-2">
                            <label for="visibilitas" class="text-xs font-medium text-ink-muted">Siapa yang bisa melihat</label>
                            <select id="visibilitas" x-on:change="$wire.ubahVisibilitas($event.target.value)"
                                    class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                                @foreach (\App\Models\Kanban\Board::VISIBILITAS as $k => $t)
                                    <option value="{{ $k }}" @selected($board->visibilitas === $k)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($kelola)
                        <button type="button" x-on:click="bagian = 'warna'" class="flex min-h-[40px] w-full items-center gap-3 rounded-lg px-3 text-left hover:bg-page">
                            <span class="h-5 w-7 rounded {{ Warna::board($board->warna) }}"></span> Ganti latar
                        </button>
                    @endif
                    <button type="button" x-on:click="bagian = 'label'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Label</button>
                    <button type="button" x-on:click="bagian = 'anggota'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Anggota ({{ $this->anggotaBoard->count() }})</button>
                    <button type="button" x-on:click="bagian = 'arsip'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Item diarsipkan</button>
                    <button type="button" x-on:click="bagian = 'aktivitas'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Aktivitas</button>
                    @if ($kelola && ! $board->isOrder() && ! $board->diarsipkan_at)
                        <hr class="my-2 border-line">
                        <button type="button" wire:click="arsipkanBoard"
                                wire:confirm="Arsipkan board ini? Board bisa dipulihkan dari halaman Semua board."
                                class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left text-[#AE2E24] hover:bg-page">Arsipkan board</button>
                    @endif
                </div>

                {{-- Warna --}}
                @if ($kelola)
                    <div x-show="bagian === 'warna'" x-cloak class="grid grid-cols-3 gap-2">
                        @foreach (Warna::BOARD as $k => [$t, $kelas])
                            <button type="button" wire:click="ubahWarna('{{ $k }}')" aria-pressed="{{ $board->warna === $k ? 'true' : 'false' }}"
                                    class="flex h-16 items-end rounded-lg p-2 text-xs font-medium text-white {{ $kelas }} {{ $board->warna === $k ? 'ring-2 ring-ink ring-offset-2' : '' }}">{{ $t }}</button>
                        @endforeach
                    </div>
                @endif

                {{-- Label --}}
                <div x-show="bagian === 'label'" x-cloak>
                    <ul class="space-y-1.5">
                        @forelse ($this->labelBoard as $l)
                            <li wire:key="ml-{{ $l->id }}" class="flex items-center gap-2" x-data="{ nama: @js($l->nama ?? '') }">
                                @if ($kelola)
                                    <input type="text" x-model="nama" x-on:change="$wire.ubahLabel({{ $l->id }}, nama)" placeholder="{{ Warna::namaLabel($l->warna) }}"
                                           aria-label="Nama label {{ Warna::namaLabel($l->warna) }}"
                                           class="h-9 min-w-0 flex-1 rounded-md border-0 text-sm font-medium placeholder:text-current/60 focus:ring-2 focus:ring-brand {{ Warna::label($l->warna) }}">
                                    <button type="button" wire:click="hapusLabel({{ $l->id }})" wire:confirm="Hapus label ini dari board dan semua kartu?"
                                            aria-label="Hapus label" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-page hover:text-[#AE2E24]">✕</button>
                                @else
                                    <span class="h-9 flex-1 truncate rounded-md px-3 font-medium leading-9 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-ink-muted">Belum ada label.</li>
                        @endforelse
                    </ul>
                    @if ($kelola)
                        <form wire:submit="tambahLabel" class="mt-4 rounded-lg bg-page p-3">
                            <h3 class="text-xs font-medium text-ink-muted">Label baru</h3>
                            <input type="text" wire:model="namaLabelBaru" placeholder="Nama (boleh kosong)" aria-label="Nama label baru"
                                   class="mt-2 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                            <div class="mt-2 grid grid-cols-5 gap-1.5">
                                @foreach (Warna::LABEL as $k => [$t, $latar])
                                    <label>
                                        <input type="radio" wire:model="warnaLabelBaru" value="{{ $k }}" class="peer sr-only">
                                        <span title="{{ $t }}" class="block h-7 cursor-pointer rounded {{ $latar }} ring-offset-1 peer-checked:ring-2 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand"></span>
                                        <span class="sr-only">{{ $t }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Buat label</button>
                        </form>
                    @endif
                </div>

                {{-- Anggota --}}
                <div x-show="bagian === 'anggota'" x-cloak>
                    @if ($board->isOrder())
                        <p class="text-ink-muted">Board order terbuka untuk semua staf.</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($this->anggotaBoard as $a)
                            <li wire:key="ma-{{ $a->id }}" class="flex min-h-[40px] items-center gap-2">
                                <x-avatar :name="$a->nama ?? $a->name" size="sm" />
                                <span class="min-w-0 flex-1 truncate">{{ $a->nama ?? $a->name }}
                                    @if ($a->pivot->peran === 'admin')<span class="text-xs text-ink-muted">· admin</span>@endif
                                </span>
                                @if ($kelola && (int) $a->id !== (int) $board->dibuat_oleh)
                                    <button type="button" wire:click="keluarkanAnggota({{ $a->id }})" wire:confirm="Keluarkan {{ $a->nama ?? $a->name }} dari board?"
                                            class="rounded-md px-2 py-1 text-xs text-ink-muted hover:bg-page hover:text-[#AE2E24]">Keluarkan</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($kelola && ! $board->isOrder())
                        <form wire:submit="tambahAnggota" class="mt-4 rounded-lg bg-page p-3">
                            <label for="anggota-baru" class="text-xs font-medium text-ink-muted">Tambah anggota</label>
                            <select id="anggota-baru" wire:model="anggotaBaru"
                                    class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                                <option value="">Pilih staf…</option>
                                @foreach ($this->calonAnggota as $u)
                                    <option value="{{ $u->id }}">{{ $u->nama ?? $u->name }}</option>
                                @endforeach
                            </select>
                            @error('anggotaBaru')<p class="mt-1 text-xs text-[#AE2E24]">Pilih staf dulu.</p>@enderror
                            <button type="submit" class="mt-2 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Tambahkan</button>
                        </form>
                    @endif
                </div>

                {{-- Arsip --}}
                <div x-show="bagian === 'arsip'" x-cloak class="space-y-5">
                    <section>
                        <h3 class="mb-1 text-xs font-medium text-ink-muted">List</h3>
                        <ul class="space-y-1">
                            @forelse ($this->arsip['kolom'] as $k)
                                <li wire:key="ak-{{ $k->id }}" class="flex min-h-[40px] items-center gap-2 rounded-lg bg-page px-3">
                                    <span class="min-w-0 flex-1 truncate">{{ $k->nama }}</span>
                                    @if ($ubah)<button type="button" wire:click="pulihkanKolom({{ $k->id }})" class="text-xs font-medium text-navy underline">Pulihkan</button>@endif
                                </li>
                            @empty
                                <li class="text-ink-muted">Tidak ada list diarsipkan.</li>
                            @endforelse
                        </ul>
                    </section>
                    <section>
                        <h3 class="mb-1 text-xs font-medium text-ink-muted">Kartu</h3>
                        <ul class="space-y-1">
                            @forelse ($this->arsip['kartu'] as $k)
                                <li wire:key="akr-{{ $k->id }}" class="flex min-h-[40px] items-center gap-2 rounded-lg bg-page px-3">
                                    <button type="button" x-on:click="menu = false" wire:click="bukaKartu({{ $k->id }})" class="min-w-0 flex-1 truncate text-left hover:underline">{{ $k->judul }}</button>
                                    @if ($ubah)<button type="button" wire:click="pulihkanKartu({{ $k->id }})" class="text-xs font-medium text-navy underline">Pulihkan</button>@endif
                                </li>
                            @empty
                                <li class="text-ink-muted">Tidak ada kartu diarsipkan.</li>
                            @endforelse
                        </ul>
                    </section>
                </div>

                {{-- Aktivitas --}}
                <ol x-show="bagian === 'aktivitas'" x-cloak class="space-y-3">
                    @forelse ($this->aktivitas as $akt)
                        <li wire:key="akt-{{ $akt->id }}" class="flex gap-2">
                            <x-avatar :name="$akt->pelaku?->nama ?? $akt->pelaku?->name ?? 'Sistem'" size="sm" />
                            <div class="min-w-0">
                                <p><span class="font-medium">{{ $akt->pelaku?->nama ?? $akt->pelaku?->name ?? 'Sistem' }}</span>
                                    {{ $akt->kalimat() }}
                                    @if ($akt->kartu)<button type="button" x-on:click="menu = false" wire:click="bukaKartu({{ $akt->kartu->id }})" class="font-medium text-navy underline">{{ $akt->kartu->judul }}</button>@endif
                                    @if ($akt->keterangan)<span class="text-ink-muted">{{ $akt->keterangan }}</span>@endif
                                </p>
                                <p class="text-xs text-ink-muted">{{ $akt->created_at?->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-ink-muted">Belum ada aktivitas.</li>
                    @endforelse
                </ol>
            </div>
        </aside>
    </div>

    @if ($kartuId)
        <livewire:kanban.detail-kartu :kartu-id="$kartuId" :key="'detail-'.$kartuId" />
    @endif
</div>
