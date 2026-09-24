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

<div class="flex h-full flex-col bg-cover bg-center {{ Warna::board($board->warna) }}"
     @if ($board->latar_path) style="background-image: url('{{ route('kanban.latar', $board) }}')" @endif
     x-data="{
        menu: false,
        saring: false,
        pewaktu: null,
        bantuan: false,
        kunciLipat: 'kanban:lipat:{{ $board->id }}',
        lipat: [],
        muatLipat() {
            try { this.lipat = JSON.parse(localStorage.getItem(this.kunciLipat) ?? '[]') } catch (e) { this.lipat = [] }
        },
        terlipat(id) { return this.lipat.includes(id) },
        sedangMengetik(e) {
            const t = e.target;
            return t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName);
        },
        pintasan(e) {
            if (e.ctrlKey || e.metaKey || e.altKey || this.sedangMengetik(e) || $wire.kartuId) return;

            if (e.key === '?') { this.bantuan = ! this.bantuan; return }
            if (e.key === '/') { e.preventDefault(); this.saring = true; $nextTick(() => document.getElementById('cari-kartu')?.focus()); return }
            if (e.key === 'f') { this.saring = ! this.saring; return }
            if (e.key === 'm') { this.menu = ! this.menu; return }
            if (e.key === '1') { $wire.gantiTampilan('papan'); return }
            if (e.key === '2') { $wire.gantiTampilan('tabel'); return }
            if (e.key === '3') { $wire.gantiTampilan('kalender'); return }
            if (e.key === '4') { $wire.gantiTampilan('linimasa'); return }
            if (e.key === '5') { $wire.gantiTampilan('dasbor'); return }
            if (e.key === 'n') { e.preventDefault(); $wire.mulaiTambahKartuPertama(); return }
            if (e.key === 'x') { $wire.bersihkanSaringan(); return }
            if (e.key === 's') { $wire.bintang(); return }
        },
        toggleLipat(id) {
            this.lipat = this.terlipat(id) ? this.lipat.filter(x => x !== id) : [...this.lipat, id];
            try { localStorage.setItem(this.kunciLipat, JSON.stringify(this.lipat)) } catch (e) {}
        },
        // --- Geser papan dengan tetikus, seperti menyeret latar papan di Trello ---
        panEl: null,
        panX: 0,
        panAwal: 0,
        panMulai(e) {
            if (e.button !== 0 || e.pointerType === 'touch') return;
            // Jangan rebut dari kartu, tombol, atau kolom yang memang bisa diseret.
            if (e.target.closest('li, button, a, input, textarea, select, form, [role=menu], [wire\\:sort\\:item]')) return;
            this.panEl = e.currentTarget;
            this.panX = e.clientX;
            this.panAwal = this.panEl.scrollLeft;
            this.panEl.style.cursor = 'grabbing';
        },
        panGerak(e) {
            if (! this.panEl) return;
            e.preventDefault();
            this.panEl.scrollLeft = this.panAwal - (e.clientX - this.panX);
        },
        panSelesai() {
            if (! this.panEl) return;
            this.panEl.style.cursor = '';
            this.panEl = null;
        },
        // --- Menu kartu (klik kanan / tombol pensil), seperti menu kartu Trello ---
        kartuMenu: null,
        sub: null,
        bukaMenuKartu(e, el) {
            e.preventDefault();
            e.stopPropagation();
            const d = el.dataset;
            const daftar = (isi) => (isi ? isi.split(',').map(Number) : []);
            const titik = e.touches?.[0] ?? e;
            this.$nextTick(() => this.rapikanMenuKartu());
            this.kartuMenu = {
                id: Number(el.getAttribute('wire:sort:item')),
                kolom: Number(d.kolom),
                judul: (el.querySelector('.judul-kartu')?.textContent ?? '').trim(),
                label: daftar(d.label),
                anggota: daftar(d.anggota),
                warna: d.warna || null,
                tenggat: d.tenggat || null,
                x: Math.max(8, Math.min(titik.clientX ?? 0, window.innerWidth - 248)),
                y: Math.min(titik.clientY ?? 0, Math.max(8, window.innerHeight - 410)),
            };
            this.sub = null;
        },
        /** Geser menu ke atas bila ujungnya tertutup bilah mengambang. */
        rapikanMenuKartu() {
            const el = this.$refs.menuKartu;
            if (! el || ! this.kartuMenu) return;
            const r = el.getBoundingClientRect();
            const batas = window.innerHeight - 80;
            if (r.bottom > batas) this.kartuMenu.y = Math.max(8, batas - r.height);
        },
        tutupMenuKartu() { this.kartuMenu = null; this.sub = null },
        punyaLabel(id) { return (this.kartuMenu?.label ?? []).includes(id) },
        punyaAnggota(id) { return (this.kartuMenu?.anggota ?? []).includes(id) },
        pilihLabel(id) {
            $wire.toggleLabelKartu(this.kartuMenu.id, id);
            this.kartuMenu.label = this.punyaLabel(id)
                ? this.kartuMenu.label.filter(x => x !== id) : [...this.kartuMenu.label, id];
        },
        pilihAnggota(id) {
            $wire.toggleAnggotaKartu(this.kartuMenu.id, id);
            this.kartuMenu.anggota = this.punyaAnggota(id)
                ? this.kartuMenu.anggota.filter(x => x !== id) : [...this.kartuMenu.anggota, id];
        },
        salinTautanKartu() {
            const tautan = location.origin + location.pathname + '?kartu=' + this.kartuMenu.id;
            const selesai = () => window.toast('Card link copied.', 'oke');
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(tautan).then(selesai).catch(() => window.toast('Could not copy the link.'));
            } else {
                const k = document.createElement('textarea');
                k.value = tautan; document.body.appendChild(k); k.select();
                document.execCommand('copy'); k.remove(); selesai();
            }
            this.tutupMenuKartu();
        },
        init() {
            this.muatLipat();
            // Periksa perubahan rekan tiap 10 detik; papan hanya digambar ulang bila memang berubah.
            this.pewaktu = setInterval(() => {
                const aktif = document.activeElement?.tagName;
                if (document.hidden || document.body.classList.contains('sorting')) return;
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(aktif)) return;
                if (this.menu) return;
                $wire.cek();
            }, 10000);
        },
        destroy() { clearInterval(this.pewaktu); },
     }" x-on:keydown.window="pintasan($event)"
     x-on:livewire-upload-error="window.toast('Background photo gagal diunggah. Coba gambar yang ukurannya lebih kecil.')">

    {{-- Kepala board --}}
    <div class="flex shrink-0 flex-wrap items-center gap-1.5 bg-black/25 px-3 py-2 text-white sm:gap-2 sm:px-4">
        @if ($kelola)
            <form wire:submit="simpanNamaBoard" class="min-w-0">
                <label for="nama-board" class="sr-only">Board name</label>
                <input id="nama-board" type="text" wire:model="namaBoard" value="{{ $namaBoard }}" x-on:blur="$wire.simpanNamaBoard()"
                       x-on:keydown.enter.prevent="$el.blur()"
                       class="w-44 rounded-md border-0 bg-transparent px-2 py-1 text-lg font-semibold text-white hover:bg-white/15 focus:bg-white focus:text-ink focus:ring-2 focus:ring-brand sm:w-auto sm:min-w-[12rem]"
                       style="field-sizing: content">
            </form>
        @else
            <h1 class="truncate px-2 py-1 text-lg font-semibold">{{ $board->nama }}</h1>
        @endif

        <button type="button" wire:click="bintang"
                aria-label="{{ $this->sayaBintang ? 'Delete bintang' : 'Beri bintang' }}" aria-pressed="{{ $this->sayaBintang ? 'true' : 'false' }}"
                class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/15 {{ $this->sayaBintang ? 'text-[#F5CD47]' : '' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $this->sayaBintang ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.13 5.11a.56.56 0 00.47.34l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.39a.56.56 0 01-.84.61l-4.73-2.89a.56.56 0 00-.58 0l-4.73 2.89a.56.56 0 01-.84-.61l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.47-.34l2.13-5.11z" /></svg>
        </button>

        <span class="hidden rounded-md bg-white/15 px-2 py-1 text-xs sm:inline">
            {{ $board->isOrder() ? 'Automatic from orders' : \App\Models\Kanban\Board::VISIBILITAS[$board->visibilitas] ?? '' }}
        </span>

        @if ($board->diarsipkan_at)
            <span class="rounded-md bg-[#F5CD47] px-2 py-1 text-xs font-medium text-ink">Board archived — read only</span>
        @endif

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            <div class="hidden -space-x-1.5 md:flex" aria-label="Board members">
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
                <button type="button" wire:click="gabung" class="h-9 rounded-md bg-white px-3 text-sm font-medium text-ink hover:bg-white/90">Join board</button>
            @endif

            {{-- Tampilan --}}
            <div class="order-last flex w-full items-center overflow-x-auto rounded-md bg-white/15 p-0.5 sm:order-none sm:w-auto" role="group" aria-label="Board view">
                @foreach (\App\Livewire\Kanban\PapanBoard::TAMPILAN as $kunci => $labelTampilan)
                    <button type="button" wire:click="gantiTampilan('{{ $kunci }}')" aria-pressed="{{ $tampilan === $kunci ? 'true' : 'false' }}"
                            @class([
                                'h-8 flex-1 rounded px-2.5 text-sm sm:flex-none',
                                'bg-white font-medium text-ink' => $tampilan === $kunci,
                                'text-white/90 hover:bg-white/15' => $tampilan !== $kunci,
                            ])>{{ $labelTampilan }}</button>
                @endforeach
            </div>

            {{-- Saring --}}
            <div class="relative">
                <button type="button" x-on:click="saring = ! saring" :aria-expanded="saring"
                        class="flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm hover:bg-white/15 {{ $this->adaSaringan ? 'bg-white text-ink hover:bg-white/90' : '' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M3 5h18M6 12h12M10 19h4" /></svg>
                    <span class="hidden sm:inline">Filter</span>
                </button>
                <div x-show="saring" x-cloak x-on:click.outside="saring = false" x-on:keydown.escape.window="saring = false"
                     class="absolute right-0 z-40 mt-2 max-h-[70vh] w-[min(20rem,calc(100vw-1.5rem))] overflow-y-auto rounded-xl border border-line bg-card p-4 text-sm text-ink shadow-lg">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Filter cards</h2>
                        @if ($this->adaSaringan)
                            <button type="button" wire:click="bersihkanSaringan" class="text-xs text-navy underline">Clear</button>
                        @endif
                    </div>
                    @if ($this->saringanTersimpan->isNotEmpty())
                        <div class="mt-3">
                            <p class="text-xs font-medium text-ink-muted">Saved filters</p>
                            <ul class="mt-1 space-y-1">
                                @foreach ($this->saringanTersimpan as $sim)
                                    <li wire:key="sim-{{ $sim->id }}" class="flex items-center gap-1">
                                        <button type="button" wire:click="pakaiSaringan({{ $sim->id }})"
                                                class="min-h-[32px] flex-1 truncate rounded-md bg-page px-2 text-left text-sm hover:bg-line">{{ $sim->nama }}</button>
                                        <button type="button" wire:click="hapusSaringan({{ $sim->id }})" aria-label="Delete filter {{ $sim->nama }}"
                                                class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-page hover:text-[#AE2E24]">✕</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label for="cari-kartu" class="mt-3 block text-xs font-medium text-ink-muted">Keyword</label>
                    <input id="cari-kartu" type="search" wire:model.live.debounce.400ms="cari" placeholder="Card title, or #123"
                           class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">

                    <fieldset class="mt-4">
                        <legend class="text-xs font-medium text-ink-muted">Due date</legend>
                        @foreach (['' => 'Semua', 'lewat' => 'Overdue', 'segera' => 'Due date 24 jam ke depan', 'selesai' => 'Ditandai selesai', 'tanpa' => 'No due date'] as $k => $t)
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

                    @if ($this->adaSaringan)
                        <form wire:submit="simpanSaringan" class="mt-4 rounded-lg bg-page p-3">
                            <label for="nama-saringan" class="text-xs font-medium text-ink-muted">Save this filter</label>
                            <div class="mt-1 flex gap-2">
                                <input id="nama-saringan" type="text" wire:model="namaSaringan" placeholder="mis. Revisi saya"
                                       class="block min-h-[36px] min-w-0 flex-1 rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                <button type="submit" class="h-9 shrink-0 rounded-md bg-navy px-3 text-sm font-medium text-white">Save</button>
                            </div>
                            @error('namaSaringan')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                        </form>
                    @endif

                    @if ($this->anggotaBoard->isNotEmpty())
                        <fieldset class="mt-4">
                            <legend class="text-xs font-medium text-ink-muted">Card members</legend>
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

            <button type="button" x-on:click="bantuan = true" aria-label="Keyboard shortcut"
                    class="hidden h-9 w-9 items-center justify-center rounded-md hover:bg-white/15 sm:flex">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="2" /><path stroke-linecap="round" d="M7 10h.01M10 10h.01M13 10h.01M16 10h.01M7 14h10" /></svg>
            </button>

            <button type="button" x-on:click="menu = true" aria-label="Board menu"
                    class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/15">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
            </button>
        </div>
    </div>

    @if ($pesan)
        <div class="mx-3 mt-2 flex items-center justify-between gap-3 rounded-lg bg-[#F5CD47] px-3 py-2 text-sm text-ink sm:mx-4" role="status">
            <span>{{ $pesan }}</span>
            <button type="button" wire:click="$set('pesan', null)" class="font-medium underline">Close</button>
        </div>
    @endif

    {{-- List & kartu --}}
    @if ($tampilan === 'papan')
    <div class="gulir-terang min-h-0 flex-1 overflow-x-auto overflow-y-hidden overscroll-x-contain"
         x-on:pointerdown="panMulai($event)" x-on:pointermove="panGerak($event)"
         x-on:pointerup.window="panSelesai()" x-on:pointercancel.window="panSelesai()" x-on:pointerleave="panSelesai()">
        <div class="flex h-full items-start gap-3 p-3 pb-16 sm:px-4">
            <ol @if ($ubah) wire:sort="urutKolom" wire:sort:config="{ handle: '.pegangan-list', delay: 220, delayOnTouchOnly: true, touchStartThreshold: 6, bubbleScroll: false }" @endif
                class="flex h-full items-start gap-3" aria-label="List">
                @foreach ($this->kolom as $kolom)
                    <li wire:key="kolom-{{ $kolom->id }}" wire:sort:item="{{ $kolom->id }}"
                        x-bind:class="terlipat({{ $kolom->id }}) ? 'w-12' : 'w-[272px]'"
                        class="flex max-h-full shrink-0 flex-col rounded-xl bg-[#F1F2F4] text-ink shadow-sm">

                        {{-- Bentuk terlipat: hanya nama & jumlah kartu, memberi ruang untuk list lain. --}}
                        <div x-show="terlipat({{ $kolom->id }})" x-cloak class="flex h-full flex-col items-center gap-2 py-2">
                            <button type="button" x-on:click="toggleLipat({{ $kolom->id }})"
                                    aria-label="Buka kembali list {{ $kolom->nama }}"
                                    class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-line hover:text-ink">»</button>
                            <span class="rounded bg-line px-1.5 text-xs text-ink-muted">{{ $kolom->jumlah_kartu }}</span>
                            <span class="mt-1 whitespace-nowrap text-sm font-semibold [writing-mode:vertical-rl]">{{ $kolom->nama }}</span>
                        </div>

                        <div x-show="! terlipat({{ $kolom->id }})" class="flex min-h-0 flex-1 flex-col">
                        @if ($kolom->warna)
                            <div class="h-1.5 rounded-t-xl {{ \App\Support\Kanban\Warna::labelLatar($kolom->warna) }}"></div>
                        @endif
                        <div class="flex items-start gap-1 px-2 pt-2"
                             x-data="{ ubah: false, nama: @js($kolom->nama), simpan() { this.ubah = false; if (this.nama.trim() && this.nama !== @js($kolom->nama)) $wire.ubahNamaKolom({{ $kolom->id }}, this.nama) } }">
                            <div @class(['pegangan-list min-w-0 flex-1 rounded-md', 'cursor-grab active:cursor-grabbing' => $ubah]) @if ($ubah) wire:sort:handle @endif>
                                <h2 x-show="! ubah" @if ($ubah) x-on:click="ubah = true; $nextTick(() => $refs.masukan.select())" @endif
                                    class="break-words px-2 py-1.5 text-sm font-semibold">{{ $kolom->nama }}</h2>
                                @if ($ubah)
                                    <input x-show="ubah" x-cloak x-ref="masukan" type="text" x-model="nama" wire:sort:ignore aria-label="List name"
                                           x-on:keydown.enter.prevent="simpan()" x-on:keydown.escape="ubah = false; nama = @js($kolom->nama)" x-on:blur="simpan()"
                                           class="block w-full rounded-md border-brand px-2 py-1 text-sm font-semibold focus:ring-brand/30">
                                @endif
                            </div>
                            <span class="mt-1.5 shrink-0 rounded px-1.5 text-xs text-ink-muted"
                                  title="{{ $kolom->jumlah_kartu }} cards in this list">{{ $kolom->jumlah_kartu }}</span>
                            {{-- Collapse list, sama seperti tombol "Collapse list" di Trello. --}}
                            <button type="button" x-on:click="toggleLipat({{ $kolom->id }})" title="Collapse list"
                                    aria-label="Collapse list {{ $kolom->nama }}" wire:sort:ignore
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-line hover:text-ink">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6l5 6-5 6M20 6l-5 6 5 6" />
                                </svg>
                            </button>
                            @if ($ubah)
                                <div class="relative shrink-0" x-data="{ buka: false }">
                                    <button type="button" x-on:click="buka = ! buka" aria-label="Menu list {{ $kolom->nama }}"
                                            class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-line hover:text-ink">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
                                    </button>
                                    <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.window="buka = false" x-effect="buka && $nextTick(() => { const r = $el.getBoundingClientRect(); $el.style.maxHeight = Math.max(180, window.innerHeight - r.top - 88) + 'px' })"
                                         class="gulir-gelap absolute left-0 z-40 mt-1 w-52 overflow-y-auto rounded-xl border border-line bg-card py-1 text-sm shadow-lg">
                                        <button type="button" x-on:click="buka = false" wire:click="mulaiTambahKartu({{ $kolom->id }})" class="block w-full px-3 py-2 text-left hover:bg-page">Add card</button>
                                        <button type="button" x-on:click="buka = false; ubah = true; $nextTick(() => $refs.masukan.select())" class="block w-full px-3 py-2 text-left hover:bg-page">Rename list</button>
                                        <button type="button" x-on:click="buka = false" wire:click="salinKolom({{ $kolom->id }})" class="block w-full px-3 py-2 text-left hover:bg-page">Copy list</button>
                                        <button type="button" x-on:click="buka = false; toggleLipat({{ $kolom->id }})" class="block w-full px-3 py-2 text-left hover:bg-page">Collapse list</button>

                                        <div class="border-t border-line px-3 py-2">
                                            <p class="text-xs font-medium text-ink-muted">List header colour</p>
                                            <div class="mt-1.5 grid grid-cols-5 gap-1">
                                                @foreach (Warna::LABEL as $w => [$namaWarna, $latarWarna])
                                                    <button type="button" wire:click="warnaKolom({{ $kolom->id }}, '{{ $w }}')" title="{{ $namaWarna }}"
                                                            aria-label="Warna {{ $namaWarna }}"
                                                            class="h-5 rounded {{ $latarWarna }} {{ $kolom->warna === $w ? 'ring-2 ring-ink ring-offset-1' : '' }}"></button>
                                                @endforeach
                                            </div>
                                            @if ($kolom->warna)
                                                <button type="button" wire:click="warnaKolom({{ $kolom->id }}, null)" class="mt-1.5 text-xs text-navy underline">Remove colour</button>
                                            @endif
                                        </div>

                                        <div class="border-t border-line px-3 py-2">
                                            <label for="urut-{{ $kolom->id }}" class="text-xs font-medium text-ink-muted">Sort cards</label>
                                            <select id="urut-{{ $kolom->id }}" x-on:change="buka = false; $wire.urutkanKartu({{ $kolom->id }}, $event.target.value); $event.target.value = ''"
                                                    class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                                <option value="" selected>Choose order…</option>
                                                @foreach (\App\Services\Kanban\Tata::URUTAN as $kunciUrut => $labelUrut)
                                                    <option value="{{ $kunciUrut }}">{{ $labelUrut }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        @if ($this->kolom->count() > 1)
                                            <div class="border-t border-line px-3 py-2">
                                                <p class="text-xs font-medium text-ink-muted">Move all cards to</p>
                                                <x-kanban.pilih-cari
                                                    :pilihan="$this->kolom->reject(fn ($t) => $t->id === $kolom->id)->map(fn ($t) => ['nilai' => $t->id, 'teks' => $t->nama])"
                                                    onpilih="buka = false; $wire.pindahSemuaKartu({{ $kolom->id }}, p.nilai)"
                                                    cari="Search list…" tinggi="max-h-40" />
                                            </div>
                                        @endif

                                        <button type="button" x-on:click="buka = false" wire:click="arsipkanSemuaKartu({{ $kolom->id }})"
                                                wire:confirm="Archive every card in list &quot;{{ $kolom->nama }}&quot;? Cards can be restored from the board menu."
                                                class="block w-full border-t border-line px-3 py-2 text-left text-[#AE2E24] hover:bg-page">Archive all cards</button>
                                        @php $isiKolom = $kolom->kartu->count(); @endphp
                                        <button type="button" x-on:click="buka = false" wire:click="hapusKolom({{ $kolom->id }})"
                                                wire:confirm="{{ $isiKolom
                                                    ? 'WARNING: list &quot;'.$kolom->nama.'&quot; still holds '.$isiKolom.' cards. Deleting it removes every card inside (along with their comments, checklists and attachments) forever. Continue?'
                                                    : 'Delete list &quot;'.$kolom->nama.'&quot; forever?' }}"
                                                class="block w-full px-3 py-2 text-left text-[#AE2E24] hover:bg-page">Delete list{{ $isiKolom ? ' + '.$isiKolom.' kartu' : '' }}</button>
                                        <button type="button" x-on:click="buka = false" wire:click="arsipkanKolom({{ $kolom->id }})"
                                                wire:confirm="Archive list &quot;{{ $kolom->nama }}&quot; and its cards? You can restore it from the board menu."
                                                class="block w-full px-3 py-2 text-left text-[#AE2E24] hover:bg-page">Archive list</button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <ol @if ($ubah) wire:sort="urutKartu" wire:sort:group="kartu" wire:sort:group-id="{{ $kolom->id }}" wire:sort:config="{ delay: 220, delayOnTouchOnly: true, touchStartThreshold: 6, bubbleScroll: false }" @endif
                            class="gulir-gelap flex min-h-[10px] flex-col gap-2 overflow-y-auto px-2 py-1" aria-label="Cards in {{ $kolom->nama }}">
                            @foreach ($kolom->kartu as $kartu)
                                @php $tenggat = $kartu->keadaanTenggat(); @endphp
                                <li wire:key="kartu-{{ $kartu->id }}" wire:sort:item="{{ $kartu->id }}"
                                    @if ($ubah)
                                        {{-- Bahan menu kartu ditulis sebagai data-*: jauh lebih ringan daripada JSON di tiap kartu. --}}
                                        data-kolom="{{ $kolom->id }}"
                                        data-label="{{ $kartu->label->pluck('id')->join(',') }}"
                                        data-anggota="{{ $kartu->anggota->pluck('id')->join(',') }}"
                                        data-warna="{{ $kartu->cover_warna }}"
                                        data-tenggat="{{ $kartu->tenggat_pada?->format('Y-m-d') }}"
                                        x-on:contextmenu="bukaMenuKartu($event, $el)"
                                    @endif
                                    class="group relative rounded-lg bg-card shadow-[0_1px_1px_rgba(9,30,66,.25)] hover:ring-2 hover:ring-brand/60">
                                    @if ($ubah)
                                        <button type="button" x-on:click="bukaMenuKartu($event, $el.closest('li'))"
                                                aria-label="Card menu" aria-haspopup="menu" wire:sort:ignore
                                                class="absolute right-1 top-1 z-10 flex h-7 w-7 items-center justify-center rounded-md bg-card/90 text-ink-muted opacity-0 shadow-sm ring-1 ring-line transition hover:text-ink focus:opacity-100 group-hover:opacity-100">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.8l3.7 3.7L8.5 19.2l-4.6.9.9-4.6z" />
                                            </svg>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="bukaKartu({{ $kartu->id }})"
                                            class="block w-full overflow-hidden rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                                        @if ($kartu->coverLampiran?->isGambar() && $kartu->cover_penuh)
                                            {{-- Sampul penuh: judul dibaca di atas gambar, seperti "full cover" Trello. --}}
                                            <span class="relative block min-h-[9rem] w-full">
                                                <img src="{{ route('kanban.lampiran', ['lampiran' => $kartu->coverLampiran, 'kecil' => 1]) }}" alt="" loading="lazy"
                                                     class="absolute inset-0 h-full w-full object-cover">
                                                <span class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent"></span>
                                                <span class="judul-kartu absolute inset-x-0 bottom-0 block px-3 pb-2 pt-6 text-sm font-medium text-white">
                                                    {{ $kartu->judul }}
                                                </span>
                                            </span>
                                        @elseif ($kartu->coverLampiran?->isGambar())
                                            <img src="{{ route('kanban.lampiran', ['lampiran' => $kartu->coverLampiran, 'kecil' => 1]) }}" alt="" loading="lazy" class="max-h-40 w-full object-cover">
                                        @elseif ($kartu->cover_warna)
                                            <span class="block h-8 {{ Warna::labelLatar($kartu->cover_warna) }}"></span>
                                        @endif
                                        <span @class(['block px-3 pb-2 pt-2', 'hidden' => $kartu->coverLampiran?->isGambar() && $kartu->cover_penuh])>
                                            @if ($kartu->label->isNotEmpty())
                                                <span class="mb-1.5 flex flex-wrap gap-1">
                                                    @foreach ($kartu->label as $l)
                                                        <span class="inline-block h-4 min-w-[2.5rem] max-w-full truncate rounded px-1.5 text-[11px] font-medium leading-4 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                                    @endforeach
                                                </span>
                                            @endif
                                            <span class="judul-kartu block break-words text-sm text-ink">{{ $kartu->judul }}</span>

                                            @if (true)
                                                <span class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-muted">
                                                    @if ($kartu->templat)
                                                        <span class="rounded bg-[#5E4DB2] px-1.5 py-0.5 font-medium text-white">Template</span>
                                                    @endif
                                                    <span class="text-ink-muted/80" title="Card number">#{{ $kartu->id }}</span>
                                                    @if ($kartu->order_id)
                                                        <span class="rounded bg-navy/10 px-1.5 py-0.5 font-medium text-navy">{{ $kartu->order?->isSusulan() ? 'Susulan' : 'Order' }}</span>
                                                    @endif
                                                    @if ($tenggat)
                                                        <span @class([
                                                            'inline-flex items-center gap-1 rounded px-1.5 py-0.5',
                                                            'bg-[#1F845A] text-white' => $tenggat === 'selesai',
                                                            'bg-[#C9372C] text-white' => $tenggat === 'lewat',
                                                            'bg-[#F5CD47] text-ink' => $tenggat === 'segera',
                                                        ]) title="Due date">
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['tenggat'] }}" /></svg>
                                                            {{ $kartu->tenggat_pada->translatedFormat('j M') }}
                                                        </span>
                                                    @endif
                                                    @if ($kartu->deskripsi)
                                                        <span title="This card has a description"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['deskripsi'] }}" /></svg><span class="sr-only">Has a description</span></span>
                                                    @endif
                                                    @if ($kartu->komentar_count)
                                                        <span class="inline-flex items-center gap-0.5" title="Comment"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon['komentar'] }}" /></svg>{{ $kartu->komentar_count }}</span>
                                                    @endif
                                                    @if ($kartu->lampiran_count)
                                                        <span class="inline-flex items-center gap-0.5" title="Attachment"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="{{ $ikon['lampiran'] }}" /></svg>{{ $kartu->lampiran_count }}</span>
                                                    @endif
                                                    @if ($kartu->checklist_item_count)
                                                        @php $beres = $kartu->checklist_selesai_count === $kartu->checklist_item_count; @endphp
                                                        <span @class(['inline-flex items-center gap-0.5 rounded px-1 py-0.5', 'bg-[#1F845A] text-white' => $beres]) title="Checklist">
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon['checklist'] }}" /></svg>{{ $kartu->checklist_selesai_count }}/{{ $kartu->checklist_item_count }}
                                                        </span>
                                                    @endif
                                                    @foreach ($kartu->bidangNilai as $isi)
                                                        @php $bd = $this->bidangDepan[$isi->bidang_id] ?? null; @endphp
                                                        @if ($bd && $isi->nilai !== null && $isi->nilai !== '')
                                                            <span class="rounded bg-[#E9EBEE] px-1.5 py-0.5" title="{{ $bd->nama }}">{{ $bd->nama }}: {{ $bd->tampilkan($isi->nilai) }}</span>
                                                        @endif
                                                    @endforeach
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

                        @php $sisaKartu = $kolom->jumlah_kartu - $kolom->kartu->count(); @endphp
                        @if ($sisaKartu > 0)
                            <button type="button" wire:click="muatLagi({{ $kolom->id }})"
                                    class="mx-2 mt-1 flex min-h-[34px] items-center justify-center rounded-lg bg-line/70 text-xs font-medium text-ink hover:bg-line">
                                <span wire:loading.remove wire:target="muatLagi({{ $kolom->id }})">Load {{ min($sisaKartu, \App\Livewire\Kanban\PapanBoard::BATAS_TAMBAH) }} more cards ({{ $sisaKartu }} left)</span>
                                <span wire:loading wire:target="muatLagi({{ $kolom->id }})">Loading…</span>
                            </button>
                        @endif

                        @if ($ubah)
                            @if ($tambahKartuDi === $kolom->id)
                                <form wire:submit="tambahKartu" class="px-2 pb-2 pt-1" x-data x-init="$nextTick(() => $refs.judul.focus())">
                                    <label for="judul-kartu-{{ $kolom->id }}" class="sr-only">Card title</label>
                                    <textarea id="judul-kartu-{{ $kolom->id }}" x-ref="judul" wire:model="judulKartuBaru" rows="2"
                                              placeholder="Enter a card title…"
                                              x-on:keydown.enter.prevent="$wire.tambahKartu().then(() => $refs.judul?.focus())"
                                              x-on:keydown.escape="$wire.batalTambahKartu()"
                                              class="block w-full resize-none rounded-lg border-0 text-sm shadow-[0_1px_1px_rgba(9,30,66,.25)] focus:ring-2 focus:ring-brand"></textarea>
                                    @error('judulKartuBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                    <div class="mt-2 flex items-center gap-1">
                                        <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Add card</button>
                                        <button type="button" wire:click="batalTambahKartu" aria-label="Cancel" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-line">✕</button>
                                        @if ($this->templat->isNotEmpty())
                                            <div class="relative ml-auto" x-data="{ buka: false }">
                                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka"
                                                        class="h-9 rounded-md px-2 text-sm text-ink-muted hover:bg-line hover:text-ink">From template</button>
                                                <ul x-show="buka" x-cloak x-on:click.outside="buka = false"
                                                    x-effect="buka && $nextTick(() => { const r = $el.getBoundingClientRect(); $el.style.maxHeight = Math.max(180, window.innerHeight - r.top - 88) + 'px' })"
                                                    class="gulir-gelap absolute right-0 z-40 mt-1 w-56 overflow-y-auto rounded-xl border border-line bg-card py-1 text-sm shadow-lg">
                                                    @foreach ($this->templat as $tpl)
                                                        <li wire:key="tpl-{{ $kolom->id }}-{{ $tpl->id }}">
                                                            <button type="button" x-on:click="buka = false" wire:click="dariTemplat({{ $tpl->id }}, {{ $kolom->id }})"
                                                                    class="block w-full truncate px-3 py-2 text-left hover:bg-page">{{ $tpl->judul }}</button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </form>
                            @else
                                <button type="button" wire:click="mulaiTambahKartu({{ $kolom->id }})"
                                        class="mx-2 mb-2 mt-1 flex min-h-[36px] items-center gap-2 rounded-lg px-2 text-left text-sm text-ink-muted hover:bg-line/70 hover:text-ink">
                                    <span aria-hidden="true" class="text-lg leading-none">+</span> Add card
                                </button>
                            @endif
                        @else
                            <div class="h-2"></div>
                        @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($ubah)
                <div class="w-[272px] shrink-0" x-data="{ buka: false }">
                    <button type="button" x-show="! buka" x-on:click="buka = true; $nextTick(() => $refs.namaList.focus())"
                            class="flex min-h-[44px] w-full items-center gap-2 rounded-xl bg-white/25 px-3 text-left text-sm font-medium text-white hover:bg-white/35">
                        <span aria-hidden="true" class="text-lg leading-none">+</span> {{ $this->kolom->isEmpty() ? 'Add list' : 'Add list lain' }}
                    </button>
                    <form x-show="buka" x-cloak wire:submit="tambahKolom" x-on:keydown.escape="buka = false"
                          class="rounded-xl bg-[#F1F2F4] p-2 shadow-sm">
                        <label for="nama-list-baru" class="sr-only">List name</label>
                        <input id="nama-list-baru" x-ref="namaList" type="text" wire:model="namaKolomBaru" placeholder="Enter a list name…"
                               class="block min-h-[36px] w-full rounded-md border-brand text-sm focus:ring-brand/30">
                        @error('namaKolomBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                        <div class="mt-2 flex items-center gap-1">
                            <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Add list</button>
                            <button type="button" x-on:click="buka = false" aria-label="Cancel" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-line">✕</button>
                        </div>
                    </form>
                </div>
            @elseif ($this->kolom->isEmpty())
                <p class="rounded-xl bg-white/90 px-4 py-3 text-sm text-ink">This board has no lists yet.</p>
            @endif
        </div>
    </div>
    @elseif ($tampilan === 'tabel')
        @include('livewire.kanban.partials.tabel')
    @elseif ($tampilan === 'linimasa')
        @include('livewire.kanban.partials.linimasa')
    @elseif ($tampilan === 'dasbor')
        @include('livewire.kanban.partials.dasbor')
    @else
        @include('livewire.kanban.partials.kalender')
    @endif

    {{-- Card menu: satu menu dipakai bersama, muncul di titik klik --}}
    @if ($ubah)
        @php
            $barisMenu = 'flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-page';
            $ikonMenu = 'h-4 w-4 shrink-0 text-ink-muted';
        @endphp
        <div x-show="kartuMenu" x-cloak x-ref="menuKartu" x-on:keydown.escape.window="tutupMenuKartu()" x-on:click.outside="tutupMenuKartu()"
             x-effect="sub; kartuMenu && $nextTick(() => rapikanMenuKartu())"
             class="gulir-gelap fixed z-40 max-h-[calc(100dvh-7rem)] w-60 overflow-y-auto rounded-xl border border-line bg-card py-1 text-sm text-ink shadow-xl"
             x-bind:style="kartuMenu ? 'left:' + kartuMenu.x + 'px; top:' + kartuMenu.y + 'px' : ''" role="menu">

            <div class="truncate border-b border-line px-3 py-2 text-xs text-ink-muted" x-text="kartuMenu?.judul"></div>

            {{-- Daftar utama --}}
            <div x-show="! sub">
                <button type="button" class="{{ $barisMenu }}" x-on:click="$wire.bukaKartu(kartuMenu.id); tutupMenuKartu()">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 5.5h16v13H4z" /><path stroke-linecap="round" d="M8 10h8M8 14h5" /></svg>
                    Open card
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="sub = 'label'">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 7.5h11l4 4.5-4 4.5H4z" /></svg>
                    Change labels
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="sub = 'anggota'">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1.5a4 4 0 00-4-4H7a4 4 0 00-4 4V19M9.5 9.5a3 3 0 100-6 3 3 0 000 6zM21 19v-1.5a4 4 0 00-3-3.9M16.5 3.7a4 4 0 010 7.6" /></svg>
                    Change members
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="sub = 'cover'">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 5.5h16v13H4z" /><path stroke-linecap="round" d="M4 11h16" /></svg>
                    Change cover
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="sub = 'tanggal'">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 6.5h16v13H4z" /><path stroke-linecap="round" d="M8 3.5v4M16 3.5v4M4 11h16" /></svg>
                    Change dates
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="sub = 'pindah'">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 6l6 6-6 6M19 12H5" /></svg>
                    Move
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="$wire.salinKartu(kartuMenu.id); tutupMenuKartu()">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M9 9h10v11H9zM5 15V4h10" /></svg>
                    Copy card
                </button>
                <button type="button" class="{{ $barisMenu }}" x-on:click="salinTautanKartu()">
                    <svg class="{{ $ikonMenu }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M10 13.5a4 4 0 006 .5l2-2a4 4 0 10-5.7-5.7l-1 1M14 10.5a4 4 0 00-6-.5l-2 2a4 4 0 105.7 5.7l1-1" /></svg>
                    Copy link
                </button>
                <button type="button" class="{{ $barisMenu }} border-t border-line text-[#AE2E24]"
                        x-on:click="$wire.arsipkanKartu(kartuMenu.id); tutupMenuKartu()">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linejoin="round" d="M4 7.5h16v3H4zM5.5 10.5h13V19h-13z" /><path stroke-linecap="round" d="M10 14h4" /></svg>
                    Archive
                </button>
            </div>

            {{-- Submenu: label --}}
            <div x-show="sub === 'label'" x-cloak class="px-3 py-2">
                <x-kanban.kembali />
                <div class="max-h-56 space-y-1 overflow-y-auto">
                    @forelse ($this->labelBoard as $l)
                        <button type="button" x-on:click="pilihLabel({{ $l->id }})" wire:key="ml-{{ $l->id }}"
                                class="flex w-full items-center gap-2 rounded px-1 py-1 text-left hover:bg-page">
                            <span class="h-6 flex-1 truncate rounded px-2 text-[11px] font-medium leading-6 {{ Warna::label($l->warna) }}">{{ $l->nama ?: Warna::namaLabel($l->warna) }}</span>
                            <svg class="h-4 w-4 text-ink" x-show="punyaLabel({{ $l->id }})" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                        </button>
                    @empty
                        <p class="text-xs text-ink-muted">This board has no labels yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Submenu: anggota --}}
            <div x-show="sub === 'anggota'" x-cloak class="px-3 py-2">
                <x-kanban.kembali />
                <div class="max-h-56 space-y-1 overflow-y-auto">
                    @forelse ($this->anggotaBoard as $a)
                        <button type="button" x-on:click="pilihAnggota({{ $a->id }})" wire:key="ma-{{ $a->id }}"
                                class="flex w-full items-center gap-2 rounded px-1 py-1 text-left hover:bg-page">
                            <x-avatar :name="$a->nama ?? $a->name" size="sm" class="!h-6 !w-6 !text-[10px]" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $a->nama ?? $a->name }}</span>
                            <svg class="h-4 w-4 text-ink" x-show="punyaAnggota({{ $a->id }})" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                        </button>
                    @empty
                        <p class="text-xs text-ink-muted">No board members yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Submenu: cover --}}
            <div x-show="sub === 'cover'" x-cloak class="px-3 py-2">
                <x-kanban.kembali />
                <div class="grid grid-cols-5 gap-1.5">
                    @foreach (Warna::LABEL as $w => [$t, $latar])
                        <button type="button" wire:key="mc-{{ $w }}" title="{{ $t }}" aria-label="Cover {{ $t }}"
                                x-on:click="$wire.sampulKartu(kartuMenu.id, @js($w)); kartuMenu.warna = @js($w)"
                                class="h-7 rounded {{ $latar }}" x-bind:class="kartuMenu?.warna === @js($w) ? 'ring-2 ring-ink ring-offset-1' : ''"></button>
                    @endforeach
                </div>
                <button type="button" x-on:click="$wire.sampulKartu(kartuMenu.id, null); kartuMenu.warna = null"
                        class="mt-2 h-8 w-full rounded-md bg-[#E9EBEE] text-xs font-medium">Remove cover</button>
                <p class="mt-1.5 text-[11px] text-ink-muted">Image covers are set inside the card.</p>
            </div>

            {{-- Submenu: tanggal --}}
            <div x-show="sub === 'tanggal'" x-cloak class="px-3 py-2">
                <x-kanban.kembali />
                <label for="tgl-menu-kartu" class="block text-xs font-medium text-ink-muted">Due date</label>
                <input id="tgl-menu-kartu" type="date" x-bind:value="kartuMenu?.tenggat ?? ''"
                       x-on:change="$wire.tenggatKartu(kartuMenu.id, $event.target.value || null); tutupMenuKartu()"
                       class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                <button type="button" x-show="kartuMenu?.tenggat"
                        x-on:click="$wire.tenggatKartu(kartuMenu.id, null); tutupMenuKartu()"
                        class="mt-2 h-8 w-full rounded-md bg-[#E9EBEE] text-xs font-medium">Remove due date</button>
            </div>

            {{-- Submenu: pindah --}}
            <div x-show="sub === 'pindah'" x-cloak class="px-3 py-2">
                <x-kanban.kembali />
                <p class="text-xs font-medium text-ink-muted">Move to list</p>
                <x-kanban.pilih-cari
                    :pilihan="$this->kolom->map(fn ($k) => ['nilai' => $k->id, 'teks' => $k->nama])"
                    onpilih="$wire.pindahKartuKe(kartuMenu.id, p.nilai); tutupMenuKartu()"
                    cari="Search list…" />
            </div>
        </div>
    @endif

    {{-- Board menu (panel samping) --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="menu = false">
        <div class="absolute inset-0 bg-ink/30" x-on:click="menu = false"></div>
        <aside x-show="menu" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full"
               x-data="{ bagian: 'utama' }" role="dialog" aria-modal="true" aria-label="Board menu"
               class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-card text-sm text-ink shadow-xl">
            <div class="flex h-12 shrink-0 items-center gap-2 border-b border-line px-2">
                <button type="button" x-show="bagian !== 'utama'" x-on:click="bagian = 'utama'" aria-label="Back"
                        class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-page">‹</button>
                <h2 class="flex-1 text-center font-semibold"
                    x-text="({ utama: 'Menu', warna: 'Change background', label: 'Label', anggota: 'Anggota', salin: 'Copy board', otomasi: 'Order card automation', arsip: 'Archived items', aktivitas: 'Aktivitas' })[bagian]">Menu</h2>
                <button type="button" x-on:click="menu = false" aria-label="Close menu" class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-page">✕</button>
            </div>

            <div class="gulir-gelap min-h-0 flex-1 overflow-y-auto p-3 pb-20">
                {{-- Utama --}}
                <div x-show="bagian === 'utama'" class="space-y-1">
                    @if ($board->isOrder())
                        <p class="mb-3 rounded-lg bg-page px-3 py-2 text-xs text-ink-muted">Cards on this board are created automatically from orders and land in each marketing list. Any staff member can move them.</p>
                    @endif
                    @if ($kelola && ! $board->isOrder())
                        <div class="mb-2 rounded-lg bg-page px-3 py-2">
                            <label for="visibilitas" class="text-xs font-medium text-ink-muted">Who can see it</label>
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
                            <span class="h-5 w-7 rounded bg-cover bg-center {{ Warna::board($board->warna) }}"
                                  @if ($board->latar_path) style="background-image: url('{{ route('kanban.latar', $board) }}')" @endif></span>
                            Change background
                        </button>
                    @endif
                    <button type="button" x-on:click="bagian = 'label'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Label</button>
                    <button type="button" x-on:click="bagian = 'anggota'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Anggota ({{ $this->anggotaBoard->count() }})</button>
                    @if ($board->isOrder() && \App\Support\Kanban\Akses::admin(auth()->user()))
                        <button type="button" x-on:click="bagian = 'otomasi'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Order card automation</button>
                    @endif
                    <button type="button" x-on:click="bagian = 'salin'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Copy board</button>
                    <a href="{{ route('kanban.ekspor', $board) }}" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Download CSV (all cards)</a>
                    <button type="button" x-on:click="bagian = 'arsip'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Archived items</button>
                    <button type="button" x-on:click="bagian = 'aktivitas'" class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left hover:bg-page">Activity</button>
                    @if ($kelola && ! $board->isOrder())
                        <hr class="my-2 border-line">
                        @if ($board->diarsipkan_at)
                            <button type="button" wire:click="hapusBoard"
                                    wire:confirm="Delete this board forever, along with every list, card, comment and attachment? This cannot be undone."
                                    class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left text-[#AE2E24] hover:bg-page">Delete board permanently</button>
                        @else
                            <button type="button" wire:click="arsipkanBoard"
                                    wire:confirm="Archive this board? You can restore it from the All boards page."
                                    class="flex min-h-[40px] w-full items-center rounded-lg px-3 text-left text-[#AE2E24] hover:bg-page">Archive board</button>
                        @endif
                    @endif
                </div>

                {{-- Warna --}}
                @if ($kelola)
                    <div x-show="bagian === 'warna'" x-cloak>
                        <div class="mb-4 rounded-lg bg-page p-3">
                            <p class="text-xs font-medium text-ink-muted">Background photo</p>
                            <label class="mt-2 flex min-h-[38px] cursor-pointer items-center justify-center rounded-md bg-card px-3 text-sm font-medium text-ink ring-1 ring-line hover:bg-page focus-within:ring-2 focus-within:ring-brand">
                                <span wire:loading.remove wire:target="latar">{{ $board->latar_path ? 'Change background photo' : 'Upload background photo' }}</span>
                                <span wire:loading wire:target="latar">Uploading…</span>
                                <input type="file" wire:model="latar" accept="image/*" class="sr-only">
                            </label>
                            <p class="mt-1 text-[11px] text-ink-muted">Photos are scaled down to 1600px so the board stays quick to open. Max {{ round((int) config('kanban.maks_lampiran_kb') / 1024) }} MB.</p>
                            @error('latar')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                            @if ($board->latar_path)
                                <button type="button" wire:click="hapusLatar" class="mt-2 text-xs text-navy underline">Remove background photo</button>
                            @endif
                        </div>

                        <p class="mb-1.5 text-xs font-medium text-ink-muted">Background colour</p>
                        <div class="grid grid-cols-3 gap-2">
                        @foreach (Warna::BOARD as $k => [$t, $kelas])
                            <button type="button" wire:click="ubahWarna('{{ $k }}')" aria-pressed="{{ $board->warna === $k ? 'true' : 'false' }}"
                                    class="flex h-16 items-end rounded-lg p-2 text-xs font-medium text-white {{ $kelas }} {{ $board->warna === $k ? 'ring-2 ring-ink ring-offset-2' : '' }}">{{ $t }}</button>
                        @endforeach
                        </div>
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
                                    <button type="button" wire:click="hapusLabel({{ $l->id }})" wire:confirm="Remove this label from the board and every card?"
                                            aria-label="Delete label" class="flex h-9 w-9 items-center justify-center rounded-md text-ink-muted hover:bg-page hover:text-[#AE2E24]">✕</button>
                                @else
                                    <span class="h-9 flex-1 truncate rounded-md px-3 font-medium leading-9 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-ink-muted">No labels yet.</li>
                        @endforelse
                    </ul>
                    @if ($kelola)
                        <form wire:submit="tambahLabel" class="mt-4 rounded-lg bg-page p-3">
                            <h3 class="text-xs font-medium text-ink-muted">Label baru</h3>
                            <input type="text" wire:model="namaLabelBaru" placeholder="Name (optional)" aria-label="New label name"
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
                            <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Create label</button>
                        </form>
                    @endif
                </div>

                {{-- Custom fields --}}
                @if ($kelola)
                    <div class="border-t border-line px-4 py-3" x-data="{ buka: false }">
                        <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="flex w-full items-center justify-between text-sm font-semibold">
                            Custom fields
                            <span class="text-xs font-normal text-ink-muted">{{ $this->bidangBoard->count() }}</span>
                        </button>
                        <div x-show="buka" x-cloak class="mt-2 space-y-2">
                            @forelse ($this->bidangBoard as $bd)
                                <div wire:key="kelola-bidang-{{ $bd->id }}" class="rounded-lg bg-page p-2">
                                    <div class="flex items-center gap-2">
                                        <input type="text" value="{{ $bd->nama }}" aria-label="Nama bidang {{ $bd->nama }}"
                                               x-on:change="$wire.ubahNamaBidang({{ $bd->id }}, $event.target.value)"
                                               class="min-h-[32px] min-w-0 flex-1 rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        <button type="button" wire:click="hapusBidang({{ $bd->id }})"
                                                wire:confirm="Delete bidang &quot;{{ $bd->nama }}&quot;? Isinya di semua kartu ikut hilang."
                                                aria-label="Delete bidang {{ $bd->nama }}"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-[#AE2E24] hover:bg-line">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M5 7h14M10 7V5h4v2M8 7l.7 12h6.6L16 7" /></svg>
                                        </button>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                                        <span>{{ \App\Models\Kanban\Bidang::JENIS[$bd->jenis] ?? $bd->jenis }}</span>
                                        @if ($bd->jenis === 'pilihan')
                                            <span class="min-w-0 truncate">{{ collect($bd->opsi)->join(', ') }}</span>
                                        @endif
                                        <label class="ml-auto inline-flex items-center gap-1.5">
                                            <input type="checkbox" wire:click="toggleDepanBidang({{ $bd->id }})" @checked($bd->di_depan)
                                                   class="h-3.5 w-3.5 rounded border-line text-navy focus:ring-brand/40">
                                            Show on card
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-ink-muted">No custom fields yet. Add your own column, e.g. "Invoice no." or "Package type".</p>
                            @endforelse

                            <form wire:submit="tambahBidang" class="rounded-lg bg-page p-2">
                                <label for="bidang-nama" class="block text-xs font-medium text-ink-muted">New field</label>
                                <input id="bidang-nama" type="text" wire:model="namaBidangBaru" placeholder="mis. No. invoice"
                                       class="mt-1 block min-h-[34px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                @error('namaBidangBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror

                                <label for="bidang-jenis" class="mt-2 block text-xs font-medium text-ink-muted">Type</label>
                                <select id="bidang-jenis" wire:model.live="jenisBidangBaru"
                                        class="mt-1 block min-h-[34px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                    @foreach (\App\Models\Kanban\Bidang::JENIS as $kunci => $labelJenis)
                                        <option value="{{ $kunci }}">{{ $labelJenis }}</option>
                                    @endforeach
                                </select>

                                @if ($jenisBidangBaru === 'pilihan')
                                    <label for="bidang-opsi" class="mt-2 block text-xs font-medium text-ink-muted">Options (one per line)</label>
                                    <textarea id="bidang-opsi" wire:model="opsiBidangBaru" rows="3" placeholder="Wisuda&#10;Graduation&#10;Yearbook"
                                              class="mt-1 block w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30"></textarea>
                                    @error('opsiBidangBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                @endif

                                <button type="submit" class="mt-2 h-8 w-full rounded-md bg-navy text-xs font-medium text-white">Add field</button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Members --}}
                <div x-show="bagian === 'anggota'" x-cloak>
                    @if ($board->isOrder())
                        <p class="text-ink-muted">The order board is open to all staff.</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($this->anggotaBoard as $a)
                            <li wire:key="ma-{{ $a->id }}" class="flex min-h-[40px] items-center gap-2">
                                <x-avatar :name="$a->nama ?? $a->name" size="sm" />
                                <span class="min-w-0 flex-1 truncate">{{ $a->nama ?? $a->name }}
                                    @if ($a->pivot->peran === 'admin')<span class="text-xs text-ink-muted">· admin</span>@endif
                                </span>
                                @if ($kelola && (int) $a->id !== (int) $board->dibuat_oleh)
                                    <button type="button" wire:click="keluarkanAnggota({{ $a->id }})" wire:confirm="Remove {{ $a->nama ?? $a->name }} dari board?"
                                            class="rounded-md px-2 py-1 text-xs text-ink-muted hover:bg-page hover:text-[#AE2E24]">Remove</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($kelola && ! $board->isOrder())
                        <form wire:submit="tambahAnggota" class="mt-4 rounded-lg bg-page p-3">
                            <label for="anggota-baru" class="text-xs font-medium text-ink-muted">Add member</label>
                            <select id="anggota-baru" wire:model="anggotaBaru"
                                    class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                                <option value="">Choose a staff member…</option>
                                @foreach ($this->calonAnggota as $u)
                                    <option value="{{ $u->id }}">{{ $u->nama ?? $u->name }}</option>
                                @endforeach
                            </select>
                            @error('anggotaBaru')<p class="mt-1 text-xs text-[#AE2E24]">Choose a staff member first.</p>@enderror
                            <button type="submit" class="mt-2 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Add</button>
                        </form>
                    @endif
                </div>

                {{-- Otomasi (board Order) --}}
                @if ($board->isOrder() && \App\Support\Kanban\Akses::admin(auth()->user()))
                    <form x-show="bagian === 'otomasi'" x-cloak wire:submit="simpanOtomasi">
                        <p class="mb-3 rounded-lg bg-page px-3 py-2 text-xs text-ink-muted">
                            Pilih list tujuan tiap milestone order. Cards move on their own once a milestone is reached, as long as
                            they are still on this board. Leave blank to keep them where they are.
                        </p>
                        @foreach (\App\Support\Kanban\OtomasiOrder::PEMICU as $pemicu => $labelPemicu)
                            <div class="mb-3" wire:key="oto-{{ $pemicu }}">
                                <label for="oto-{{ $pemicu }}" class="block text-xs font-medium text-ink-muted">{{ $labelPemicu }}</label>
                                <select id="oto-{{ $pemicu }}" wire:model="otomasi.{{ $pemicu }}"
                                        class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                                    <option value="">— not moved —</option>
                                    @foreach ($this->kolom as $kol)
                                        <option value="{{ $kol->id }}">{{ $kol->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Save automation</button>
                    </form>
                @endif

                {{-- Salin board --}}
                <form x-show="bagian === 'salin'" x-cloak wire:submit="salinBoard">
                    <label for="nama-salinan-board" class="block text-xs font-medium text-ink-muted">New board name</label>
                    <input id="nama-salinan-board" type="text" wire:model="namaSalinanBoard"
                           class="mt-1 block min-h-[40px] w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30">
                    @error('namaSalinanBoard')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                    <label class="mt-3 flex min-h-[36px] items-center gap-2">
                        <input type="checkbox" wire:model="salinDenganKartu" class="rounded text-brand focus:ring-brand/30">
                        Copy the cards too
                    </label>
                    <p class="mt-1 text-xs text-ink-muted">Order cards are not copied — one order may only have one card.</p>
                    <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Copy board</button>
                </form>

                {{-- Arsip --}}
                <div x-show="bagian === 'arsip'" x-cloak class="space-y-5">
                    <section>
                        <h3 class="mb-1 text-xs font-medium text-ink-muted">List</h3>
                        <ul class="space-y-1">
                            @forelse ($this->arsip['kolom'] as $k)
                                <li wire:key="ak-{{ $k->id }}" class="flex min-h-[40px] items-center gap-2 rounded-lg bg-page px-3">
                                    <span class="min-w-0 flex-1 truncate">{{ $k->nama }}</span>
                                    @if ($ubah)<button type="button" wire:click="pulihkanKolom({{ $k->id }})" class="text-xs font-medium text-navy underline">Restore</button>@endif
                                </li>
                            @empty
                                <li class="text-ink-muted">No archived lists.</li>
                            @endforelse
                        </ul>
                    </section>
                    <section>
                        <h3 class="mb-1 text-xs font-medium text-ink-muted">Cards</h3>
                        <ul class="space-y-1">
                            @forelse ($this->arsip['kartu'] as $k)
                                <li wire:key="akr-{{ $k->id }}" class="flex min-h-[40px] items-center gap-2 rounded-lg bg-page px-3">
                                    <button type="button" x-on:click="menu = false" wire:click="bukaKartu({{ $k->id }})" class="min-w-0 flex-1 truncate text-left hover:underline">{{ $k->judul }}</button>
                                    @if ($ubah)<button type="button" wire:click="pulihkanKartu({{ $k->id }})" class="text-xs font-medium text-navy underline">Restore</button>@endif
                                </li>
                            @empty
                                <li class="text-ink-muted">No archived cards.</li>
                            @endforelse
                        </ul>
                    </section>
                </div>

                {{-- Activity --}}
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
                        <li class="text-ink-muted">No activity yet.</li>
                    @endforelse

                    @if ($this->aktivitas->count() >= $jumlahAktivitas && $jumlahAktivitas < 200)
                        <li>
                            <button type="button" wire:click="aktivitasLagi"
                                    class="min-h-[36px] w-full rounded-lg bg-page text-sm font-medium text-navy hover:bg-line">Load more</button>
                        </li>
                    @endif
                </ol>
            </div>
        </aside>
    </div>

    {{-- Daftar pintasan papan ketik --}}
    <div x-show="bantuan" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
         x-on:keydown.escape.window="bantuan = false" role="dialog" aria-modal="true" aria-labelledby="judul-pintasan">
        <div class="absolute inset-0 bg-ink/40" x-on:click="bantuan = false"></div>
        <div class="relative w-full max-w-md rounded-t-2xl border border-line bg-card p-5 text-sm text-ink shadow-lg sm:rounded-2xl">
            <div class="flex items-center justify-between">
                <h2 id="judul-pintasan" class="text-base font-semibold">Keyboard shortcut</h2>
                <button type="button" x-on:click="bantuan = false" aria-label="Close" class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-page">✕</button>
            </div>
            <dl class="mt-3 grid grid-cols-[4.5rem_minmax(0,1fr)] gap-y-2">
                @foreach ([
                    'n' => 'Add a card to the first list',
                    '/' => 'Search cards on this board',
                    'f' => 'Open / close the filter',
                    'm' => 'Open / close the board menu',
                    'x' => 'Clear the filter',
                    's' => 'Star / unstar the board',
                    '1 … 5' => 'Board, Table, Calendar, Timeline, Dashboard',
                    '?' => 'Show this list',
                ] as $tombol => $arti)
                    <dt><kbd class="rounded border border-line bg-page px-1.5 py-0.5 font-mono text-xs">{{ $tombol }}</kbd></dt>
                    <dd class="text-ink-muted">{{ $arti }}</dd>
                @endforeach
            </dl>
            <h3 class="mt-4 text-sm font-semibold">While a card is open</h3>
            <dl class="mt-2 grid grid-cols-[4.5rem_minmax(0,1fr)] gap-y-2">
                @foreach ([
                    'space' => 'Assign / unassign yourself',
                    'e' => 'Edit the description',
                    'w' => 'Follow / unfollow the card',
                    'c' => 'Archive the card',
                    'esc' => 'Close the card',
                ] as $tombol => $arti)
                    <dt><kbd class="rounded border border-line bg-page px-1.5 py-0.5 font-mono text-xs">{{ $tombol }}</kbd></dt>
                    <dd class="text-ink-muted">{{ $arti }}</dd>
                @endforeach
            </dl>
        </div>
    </div>

    @if ($kartuId)
        <livewire:kanban.detail-kartu :kartu-id="$kartuId" :key="'detail-'.$kartuId" />
    @endif
</div>
