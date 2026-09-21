@php
    use App\Support\Kanban\Warna;
    $k = $this->kartu;
    $ubah = $this->bolehUbah;
    $tenggat = $k->keadaanTenggat();
    $tombol = 'flex min-h-[36px] w-full items-center gap-2 rounded-md bg-[#E9EBEE] px-3 text-left text-sm font-medium text-ink hover:bg-[#DCDFE4]';
    $panel = 'absolute right-0 z-20 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-line bg-card p-3 text-sm shadow-lg';
@endphp

<div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="judul-kartu"
     x-data="{
        pewaktu: null,
        seret: false,
        bolehUnggah: @js($ubah),
        init() {
            // Ikut memantau perubahan rekan (komentar, checklist) selama kartu terbuka.
            this.pewaktu = setInterval(() => {
                const aktif = document.activeElement?.tagName;
                if (document.hidden || ['INPUT', 'TEXTAREA', 'SELECT'].includes(aktif)) return;
                $wire.cek();
            }, 8000);
        },
        destroy() { clearInterval(this.pewaktu); },
        sedangMengetik(e) {
            const t = e.target;
            return t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName);
        },
        pintasan(e) {
            if (e.ctrlKey || e.metaKey || e.altKey || this.sedangMengetik(e) || ! this.bolehUnggah) return;

            if (e.key === ' ') { e.preventDefault(); $wire.toggleAnggota(@js(auth()->id())); return }
            if (e.key === 'w') { $wire.toggleIkut(); return }
            if (e.key === 'c') { $wire.arsipkan(); return }
            if (e.key === 'e') {
                e.preventDefault();
                $wire.set('ubahDeskripsi', true).then(() => document.getElementById('deskripsi-kartu')?.focus());
            }
        },
        adaBerkas(e) {
            return this.bolehUnggah && Array.from(e.dataTransfer?.types ?? []).includes('Files');
        },
        unggah(daftar) {
            const berkas = Array.from(daftar ?? []);
            this.seret = false;
            if (! this.bolehUnggah || berkas.length === 0) return;
            $wire.uploadMultiple('berkas', berkas, () => {}, () => {});
        },
     }"
     x-on:dragenter.prevent="if (adaBerkas($event)) seret = true"
     x-on:dragover.prevent="if (adaBerkas($event)) seret = true"
     x-on:dragleave="if ($event.relatedTarget === null) seret = false"
     x-on:drop.prevent.stop="unggah($event.dataTransfer?.files)"
     x-on:paste="unggah($event.clipboardData?.files)"
     x-on:keydown.escape="$wire.tutup()"
     x-on:keydown.window="pintasan($event)">

    {{-- Seret berkas ke mana saja di kartu untuk melampirkan. --}}
    <div x-show="seret" x-cloak class="pointer-events-none fixed inset-0 z-[60] flex items-center justify-center bg-navy/50 p-6">
        <p class="rounded-2xl border-2 border-dashed border-white bg-card px-6 py-5 text-center text-sm font-medium text-ink shadow-lg">
            Lepaskan berkas di sini untuk melampirkan ke kartu ini
        </p>
    </div>
    <div class="fixed inset-0 bg-black/50" wire:click="tutup"></div>

    <div class="relative mx-auto my-0 w-full max-w-3xl bg-[#F1F2F4] text-ink sm:my-12 sm:rounded-2xl">
        @if ($k->coverLampiran?->isGambar())
            <div class="flex h-40 items-center justify-center overflow-hidden bg-[#DCDFE4] sm:rounded-t-2xl">
                <img src="{{ route('kanban.lampiran', $k->coverLampiran) }}" alt="" class="h-full object-contain">
            </div>
        @elseif ($k->cover_warna)
            <div class="h-24 sm:rounded-t-2xl {{ Warna::labelLatar($k->cover_warna) }}"></div>
        @endif

        <button type="button" wire:click="tutup" aria-label="Tutup kartu"
                class="absolute right-2 top-2 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-black/10 text-lg hover:bg-black/20">✕</button>

        @if ($k->diarsipkan_at)
            <div class="flex flex-wrap items-center gap-3 bg-[#F5CD47] px-5 py-3 text-sm {{ $k->coverLampiran || $k->cover_warna ? '' : 'sm:rounded-t-2xl' }}">
                <span class="font-medium">Kartu ini diarsipkan.</span>
                @if (\App\Support\Kanban\Akses::bolehUbah(auth()->user(), $k->board))
                    <button type="button" wire:click="pulihkan" class="rounded-md bg-white/70 px-3 py-1.5 font-medium hover:bg-white">Pulihkan</button>
                    @if (! $k->order_id)
                        <button type="button" wire:click="hapus" wire:confirm="Hapus kartu ini selamanya? Tindakan ini tidak bisa dibatalkan."
                                class="rounded-md bg-[#C9372C] px-3 py-1.5 font-medium text-white hover:bg-[#AE2E24]">Hapus permanen</button>
                    @endif
                @endif
                @error('arsip')<span class="w-full text-[#AE2E24]">{{ $message }}</span>@enderror
            </div>
        @endif

        <div class="px-4 pb-6 pt-5 sm:px-6">
            {{-- Judul --}}
            <div class="pr-10">
                @if ($ubah)
                    <label for="judul-kartu" class="sr-only">Judul kartu</label>
                    <textarea id="judul-kartu" wire:model="judul" rows="1" x-on:blur="$wire.simpanJudul()" x-on:keydown.enter.prevent="$el.blur()"
                              class="block w-full resize-none rounded-md border-0 bg-transparent px-2 py-1 text-xl font-semibold hover:bg-white/60 focus:bg-white focus:ring-2 focus:ring-brand"
                              style="field-sizing: content">{{ $judul }}</textarea>
                @else
                    <h2 id="judul-kartu" class="px-2 py-1 text-xl font-semibold">{{ $k->judul }}</h2>
                @endif
                <p class="px-2 text-sm text-ink-muted">di list <span class="font-medium text-ink">{{ $k->kolom?->nama }}</span> · board {{ $k->board->nama }}
                    @if ($k->templat)<span class="ml-1 rounded bg-[#5E4DB2] px-1.5 py-0.5 text-xs font-medium text-white">Templat</span>@endif
                    @if ($this->mengikuti)<span class="ml-1 rounded bg-[#E9EBEE] px-1.5 py-0.5 text-xs text-ink">Diikuti</span>@endif
                </p>
            </div>

            <div class="mt-5 grid gap-6 md:grid-cols-[minmax(0,1fr)_11rem]">
                {{-- Kolom utama --}}
                <div class="min-w-0 space-y-6">
                    {{-- Label, anggota, tanggal --}}
                    <div class="flex flex-wrap gap-x-6 gap-y-4 px-2">
                        @if ($k->anggota->isNotEmpty())
                            <div>
                                <h3 class="mb-1 text-xs font-medium text-ink-muted">Anggota</h3>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($k->anggota as $a)
                                        <span title="{{ $a->nama ?? $a->name }}"><x-avatar :name="$a->nama ?? $a->name" size="sm" /></span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($k->label->isNotEmpty())
                            <div>
                                <h3 class="mb-1 text-xs font-medium text-ink-muted">Label</h3>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($k->label as $l)
                                        <span class="inline-flex h-8 min-w-[3rem] items-center rounded px-3 text-sm font-medium {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($k->tenggat_pada || $k->mulai_pada)
                            <div>
                                <h3 class="mb-1 text-xs font-medium text-ink-muted">{{ $k->tenggat_pada ? 'Tenggat' : 'Mulai' }}</h3>
                                <div class="flex items-center gap-2">
                                    @if ($k->tenggat_pada)
                                        <input type="checkbox" @checked($k->tenggat_selesai_at) @disabled(! $ubah) wire:click="toggleTenggatSelesai"
                                               aria-label="Tandai tenggat selesai" class="h-5 w-5 rounded text-[#1F845A] focus:ring-brand/30">
                                    @endif
                                    <span class="rounded-md bg-[#E9EBEE] px-3 py-1.5 text-sm">
                                        @if ($k->mulai_pada){{ $k->mulai_pada->translatedFormat('j M') }}@if ($k->tenggat_pada) – @endif @endif
                                        @if ($k->tenggat_pada){{ $k->tenggat_pada->translatedFormat('j M Y, H:i') }}@endif
                                        @if ($tenggat === 'selesai')<span class="ml-1 rounded bg-[#1F845A] px-1.5 text-xs text-white">Selesai</span>
                                        @elseif ($tenggat === 'lewat')<span class="ml-1 rounded bg-[#C9372C] px-1.5 text-xs text-white">Lewat</span>
                                        @elseif ($tenggat === 'segera')<span class="ml-1 rounded bg-[#F5CD47] px-1.5 text-xs">Segera</span>@endif
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Order --}}
                    @if ($k->order_id)
                        <section class="rounded-xl border border-navy/20 bg-white p-4">
                            <h3 class="text-sm font-semibold text-navy">{{ $k->order?->isSusulan() ? 'Order susulan' : 'Order' }}</h3>
                            @if ($k->order)
                                <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                    <dt class="text-ink-muted">Kode</dt><dd class="font-medium">{{ $k->order->booking_code }}</dd>
                                    <dt class="text-ink-muted">Sekolah</dt><dd>{{ $k->order->sekolah?->nama ?? '—' }}</dd>
                                    <dt class="text-ink-muted">Marketing</dt><dd>{{ $k->order->marketing?->nama ?? $k->order->marketing?->name ?? '—' }}</dd>
                                    <dt class="text-ink-muted">Cabang</dt><dd>{{ $k->order->cabang?->nama ?? '—' }}</dd>
                                    <dt class="text-ink-muted">Status</dt><dd>{{ $k->order->statusLabel() }}</dd>
                                    <dt class="text-ink-muted">Tanggal event</dt><dd>{{ $k->order->tanggal_event?->translatedFormat('j M Y') ?? 'Belum diatur' }}</dd>
                                </dl>
                                <a href="{{ route('app.order.show', $k->order_id) }}" target="_blank" rel="noopener"
                                   class="mt-3 inline-flex min-h-[36px] items-center rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Buka order di panel staf ↗</a>
                            @else
                                <p class="mt-1 text-sm text-ink-muted">Order ini di luar cabang Anda atau sudah dihapus.</p>
                            @endif
                        </section>
                    @endif

                    {{-- Deskripsi --}}
                    <section>
                        <div class="flex items-center justify-between gap-2 px-2">
                            <h3 class="text-base font-semibold">Deskripsi</h3>
                            @if ($ubah && ! $ubahDeskripsi && $k->deskripsi)
                                <button type="button" wire:click="$set('ubahDeskripsi', true)" class="rounded-md bg-[#E9EBEE] px-3 py-1.5 text-sm font-medium hover:bg-[#DCDFE4]">Ubah</button>
                            @endif
                        </div>
                        @if ($ubahDeskripsi && $ubah)
                            <form wire:submit="simpanDeskripsi" class="mt-2 px-2">
                                <label for="deskripsi-kartu" class="sr-only">Deskripsi</label>
                                <textarea id="deskripsi-kartu" wire:model="deskripsi" rows="6" x-init="$el.focus()"
                                          class="block w-full rounded-lg border-line text-sm focus:border-brand focus:ring-brand/30"
                                          placeholder="Tambahkan deskripsi yang lebih rinci…"></textarea>
                                <p class="mt-1 text-[11px] text-ink-muted">Format: {{ \App\Support\Kanban\Teks::BANTUAN }}</p>
                                @error('deskripsi')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                <div class="mt-2 flex gap-2">
                                    <button type="submit" class="h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Simpan</button>
                                    <button type="button" wire:click="batalDeskripsi" class="h-9 rounded-md px-3 text-sm hover:bg-[#DCDFE4]">Batal</button>
                                </div>
                            </form>
                        @elseif ($k->deskripsi)
                            <div class="isi-teks mt-2 break-words px-2 text-sm leading-relaxed">{!! \App\Support\Kanban\Teks::html($k->deskripsi) !!}</div>
                        @elseif ($ubah)
                            <button type="button" wire:click="$set('ubahDeskripsi', true)"
                                    class="mx-2 mt-2 block min-h-[56px] w-[calc(100%-1rem)] rounded-lg bg-[#E9EBEE] px-3 text-left text-sm text-ink-muted hover:bg-[#DCDFE4]">
                                Tambahkan deskripsi yang lebih rinci…
                            </button>
                        @else
                            <p class="mt-2 px-2 text-sm text-ink-muted">Tanpa deskripsi.</p>
                        @endif
                    </section>

                    {{-- Lampiran --}}
                    @if ($k->lampiran->isNotEmpty())
                        <section>
                            <h3 class="px-2 text-base font-semibold">Lampiran</h3>
                            <ul class="mt-2 space-y-2 px-2">
                                @foreach ($k->lampiran as $lp)
                                    <li wire:key="lp-{{ $lp->id }}" class="flex gap-3">
                                        <a href="{{ $lp->isTautan() ? $lp->url : route('kanban.lampiran', $lp) }}" target="_blank" rel="noopener noreferrer"
                                           class="flex h-16 w-24 shrink-0 items-center justify-center overflow-hidden rounded-md bg-[#DCDFE4] text-xs font-semibold text-ink-muted">
                                            @if ($lp->isGambar())
                                                <img src="{{ route('kanban.lampiran', $lp) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                            @else
                                                {{ $lp->ekstensi() }}
                                            @endif
                                        </a>
                                        <div class="min-w-0 flex-1 text-sm">
                                            <a href="{{ $lp->isTautan() ? $lp->url : route('kanban.lampiran', $lp) }}" target="_blank" rel="noopener noreferrer" class="block truncate font-medium hover:underline">{{ $lp->nama }}</a>
                                            <p class="truncate text-xs text-ink-muted">
                                                {{ $lp->created_at?->diffForHumans() }} · {{ $lp->pengunggah?->nama ?? $lp->pengunggah?->name }}
                                                @if ($lp->isTautan()) · {{ $lp->url }} @else · {{ \Illuminate\Support\Number::fileSize((int) $lp->ukuran) }} @endif
                                            </p>
                                            <div class="mt-1 flex flex-wrap gap-x-3 text-xs">
                                                @unless ($lp->isTautan())
                                                    <a href="{{ route('kanban.lampiran', ['lampiran' => $lp, 'unduh' => 1]) }}" class="text-navy underline">Unduh</a>
                                                @endunless
                                                @if ($ubah && $lp->isGambar())
                                                    @if ((int) $k->cover_lampiran_id === $lp->id)
                                                        <button type="button" wire:click="jadikanSampul(null)" class="text-navy underline">Lepas sampul</button>
                                                    @else
                                                        <button type="button" wire:click="jadikanSampul({{ $lp->id }})" class="text-navy underline">Jadikan sampul</button>
                                                    @endif
                                                @endif
                                                @if ($ubah && ((int) $lp->user_id === (int) auth()->id() || $this->admin))
                                                    <button type="button" wire:click="hapusLampiran({{ $lp->id }})" wire:confirm="Hapus lampiran {{ $lp->nama }}?" class="text-[#AE2E24] underline">Hapus</button>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Checklist --}}
                    @foreach ($k->checklist as $cl)
                        @php
                            $jumlah = $cl->item->count();
                            $selesai = $cl->item->whereNotNull('selesai_at')->count();
                            $persen = $jumlah ? (int) round($selesai / $jumlah * 100) : 0;
                        @endphp
                        <section wire:key="cl-{{ $cl->id }}" x-data="{ sembunyi: false }">
                            <div class="flex items-center gap-2 px-2">
                                @if ($ubah)
                                    <input type="text" value="{{ $cl->judul }}" aria-label="Judul checklist"
                                           x-on:change="$wire.ubahJudulChecklist({{ $cl->id }}, $event.target.value)"
                                           class="min-w-0 flex-1 rounded-md border-0 bg-transparent px-1 py-1 text-base font-semibold hover:bg-white/60 focus:bg-white focus:ring-2 focus:ring-brand">
                                @else
                                    <h3 class="flex-1 text-base font-semibold">{{ $cl->judul }}</h3>
                                @endif
                                @if ($selesai)
                                    <button type="button" x-on:click="sembunyi = ! sembunyi" class="rounded-md bg-[#E9EBEE] px-2 py-1.5 text-xs font-medium hover:bg-[#DCDFE4]"
                                            x-text="sembunyi ? 'Tampilkan yang selesai ({{ $selesai }})' : 'Sembunyikan yang selesai'">Sembunyikan yang selesai</button>
                                @endif
                                @if ($ubah)
                                    <button type="button" wire:click="hapusChecklist({{ $cl->id }})" wire:confirm="Hapus checklist &quot;{{ $cl->judul }}&quot;?"
                                            class="rounded-md bg-[#E9EBEE] px-2 py-1.5 text-xs font-medium hover:bg-[#DCDFE4]">Hapus</button>
                                @endif
                            </div>
                            <div class="mt-2 flex items-center gap-2 px-2">
                                <span class="w-9 text-xs text-ink-muted">{{ $persen }}%</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-[#DCDFE4]" role="progressbar" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100" aria-label="Kemajuan {{ $cl->judul }}">
                                    <div class="h-full rounded-full {{ $persen === 100 ? 'bg-[#1F845A]' : 'bg-navy' }}" style="width: {{ $persen }}%"></div>
                                </div>
                            </div>
                            <ul @if ($ubah) wire:sort="urutItem" wire:sort:group="checklist" wire:sort:group-id="{{ $cl->id }}" @endif class="mt-1 min-h-[1.5rem]">
                                @foreach ($cl->item as $it)
                                    <li wire:key="it-{{ $it->id }}" wire:sort:item="{{ $it->id }}" class="group flex items-start gap-2 rounded-md px-2 py-1 hover:bg-[#E9EBEE]"
                                        @if ($it->selesai_at) x-show="! sembunyi" @endif
                                        x-data="{ ubah: false, teks: @js($it->teks) }">
                                        <input type="checkbox" @checked($it->selesai_at) @disabled(! $ubah) wire:click="toggleItem({{ $it->id }})"
                                               aria-label="Selesai: {{ $it->teks }}" class="mt-1.5 h-4 w-4 rounded text-[#1F845A] focus:ring-brand/30">
                                        <div class="min-w-0 flex-1">
                                            <p x-show="! ubah" @if ($ubah) x-on:click="ubah = true; $nextTick(() => $refs.teks.focus())" @endif
                                               @class(['break-words py-1 text-sm', 'text-ink-muted line-through' => $it->selesai_at, 'cursor-text' => $ubah])>{{ $it->teks }}</p>
                                            @if ($ubah)
                                                <input x-show="ubah" x-cloak x-ref="teks" type="text" x-model="teks" aria-label="Ubah item"
                                                       x-on:keydown.enter.prevent="ubah = false; $wire.ubahItem({{ $it->id }}, teks)"
                                                       x-on:keydown.escape.stop="ubah = false; teks = @js($it->teks)"
                                                       x-on:blur="ubah = false; if (teks !== @js($it->teks)) $wire.ubahItem({{ $it->id }}, teks)"
                                                       class="block w-full rounded-md border-brand py-1 text-sm focus:ring-brand/30">
                                            @endif
                                            @php $tenggatItem = $it->keadaanTenggat(); @endphp
                                            @if ($it->petugas || $tenggatItem || $it->selesai_at)
                                                <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-ink-muted">
                                                    @if ($it->petugas)
                                                        <span class="inline-flex items-center gap-1 rounded bg-[#DCDFE4] px-1.5 py-0.5 text-ink">
                                                            <x-avatar :name="$it->petugas->nama ?? $it->petugas->name" size="sm" class="!h-4 !w-4 !text-[8px]" />
                                                            {{ $it->petugas->nama ?? $it->petugas->name }}
                                                        </span>
                                                    @endif
                                                    @if ($tenggatItem)
                                                        <span @class([
                                                            'inline-flex items-center rounded px-1.5 py-0.5',
                                                            'bg-[#1F845A] text-white' => $tenggatItem === 'selesai',
                                                            'bg-[#C9372C] text-white' => $tenggatItem === 'lewat',
                                                            'bg-[#F5CD47] text-ink' => $tenggatItem === 'segera',
                                                            'bg-[#DCDFE4] text-ink' => $tenggatItem === 'biasa',
                                                        ])>{{ $it->tenggat_pada->translatedFormat('j M, H:i') }}</span>
                                                    @endif
                                                    @if ($it->selesai_at)
                                                        <span>selesai {{ $it->selesai_at->diffForHumans() }}</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>

                                        @if ($ubah)
                                            {{-- Tugaskan item --}}
                                            <div class="relative shrink-0" x-data="{ buka: false }">
                                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka"
                                                        aria-label="Tugaskan item {{ $it->teks }}"
                                                        class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-[#DCDFE4] hover:text-ink">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3.2" /><path stroke-linecap="round" d="M5 19.5c1.6-3 4-4.5 7-4.5s5.4 1.5 7 4.5" /></svg>
                                                </button>
                                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu
                                                     class="{{ $panel }} w-60">
                                                    <h5 class="text-center font-semibold">Tugaskan ke</h5>
                                                    <ul class="mt-2 max-h-56 overflow-y-auto">
                                                        @foreach ($this->calonAnggota as $u)
                                                            <li wire:key="tugas-{{ $it->id }}-{{ $u->id }}">
                                                                <button type="button" x-on:click="buka = false" wire:click="tugaskanItem({{ $it->id }}, {{ $u->id }})"
                                                                        class="flex min-h-[36px] w-full items-center gap-2 rounded-md px-2 text-left hover:bg-page">
                                                                    <x-avatar :name="$u->nama ?? $u->name" size="sm" class="!h-6 !w-6 !text-[10px]" />
                                                                    <span class="min-w-0 flex-1 truncate">{{ $u->nama ?? $u->name }}</span>
                                                                    @if ((int) $it->user_id === (int) $u->id)<span class="text-navy" aria-hidden="true">✓</span>@endif
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    @if ($it->user_id)
                                                        <button type="button" x-on:click="buka = false" wire:click="tugaskanItem({{ $it->id }}, null)"
                                                                class="mt-2 h-8 w-full rounded-md bg-[#E9EBEE] text-xs font-medium">Lepas tugas</button>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Tenggat item --}}
                                            <div class="relative shrink-0" x-data="{ buka: false, nilai: @js(optional($it->tenggat_pada)->format('Y-m-d\TH:i') ?? '') }">
                                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka"
                                                        aria-label="Tenggat item {{ $it->teks }}"
                                                        class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-[#DCDFE4] hover:text-ink">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M12 7v5l3 1.5m6-1.5a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </button>
                                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu
                                                     class="{{ $panel }} w-64">
                                                    <h5 class="text-center font-semibold">Tenggat item</h5>
                                                    <label for="tenggat-item-{{ $it->id }}" class="sr-only">Tenggat item {{ $it->teks }}</label>
                                                    <input id="tenggat-item-{{ $it->id }}" type="datetime-local" x-model="nilai"
                                                           class="mt-2 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                                    <div class="mt-2 flex gap-2">
                                                        <button type="button" x-on:click="buka = false; $wire.tenggatItem({{ $it->id }}, nilai || null)"
                                                                class="h-8 flex-1 rounded-md bg-navy text-xs font-medium text-white">Simpan</button>
                                                        <button type="button" x-on:click="nilai = ''; buka = false; $wire.tenggatItem({{ $it->id }}, null)"
                                                                class="h-8 rounded-md bg-[#E9EBEE] px-2 text-xs">Hapus</button>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Menu item --}}
                                            <div class="relative shrink-0" x-data="{ buka: false }">
                                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" aria-label="Menu item {{ $it->teks }}"
                                                        class="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-[#DCDFE4] hover:text-ink">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
                                                </button>
                                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu
                                                     class="absolute right-0 z-20 mt-1 w-48 rounded-xl border border-line bg-card py-1 text-sm shadow-lg">
                                                    <button type="button" x-on:click="buka = false" wire:click="itemJadiKartu({{ $it->id }})"
                                                            class="block w-full px-3 py-2 text-left hover:bg-page">Jadikan kartu</button>
                                                    <button type="button" x-on:click="buka = false" wire:click="hapusItem({{ $it->id }})"
                                                            class="block w-full px-3 py-2 text-left text-[#AE2E24] hover:bg-page">Hapus item</button>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($ubah)
                                <form wire:submit="tambahItem({{ $cl->id }})" class="mt-1 flex gap-2 px-2 pl-8">
                                    <label for="item-baru-{{ $cl->id }}" class="sr-only">Item baru</label>
                                    <input id="item-baru-{{ $cl->id }}" type="text" wire:model="itemBaru.{{ $cl->id }}" placeholder="Tambah item…"
                                           class="block min-h-[36px] min-w-0 flex-1 rounded-md border-line bg-white text-sm focus:border-brand focus:ring-brand/30">
                                    <button type="submit" class="h-9 shrink-0 rounded-md bg-[#E9EBEE] px-3 text-sm font-medium hover:bg-[#DCDFE4]">Tambah</button>
                                </form>
                            @endif
                        </section>
                    @endforeach

                    {{-- Komentar & aktivitas --}}
                    <section>
                        <h3 class="px-2 text-base font-semibold">Komentar & aktivitas</h3>
                        @if ($ubah)
                            <form wire:submit="kirimKomentar" class="mt-2 flex gap-2 px-2">
                                <x-avatar :name="auth()->user()->nama ?? auth()->user()->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <label for="komentar-baru" class="sr-only">Tulis komentar</label>
                                    <div class="relative"
                                         x-data="{
                                            daftar: @js($this->namaCalon),
                                            cocok: [],
                                            cari() {
                                                const el = $refs.komentar;
                                                const m = el.value.slice(0, el.selectionStart).match(/@([\p{L}\p{N}]*)$/u);
                                                this.cocok = m
                                                    ? this.daftar.filter(n => n.toLowerCase().includes(m[1].toLowerCase())).slice(0, 6)
                                                    : [];
                                            },
                                            pilih(nama) {
                                                const el = $refs.komentar;
                                                const akhir = el.selectionStart;
                                                const sebelum = el.value.slice(0, akhir).replace(/@([\p{L}\p{N}]*)$/u, '@' + nama.replace(/\s+/g, '') + ' ');
                                                el.value = sebelum + el.value.slice(akhir);
                                                el.dispatchEvent(new Event('input'));
                                                el.focus();
                                                el.selectionStart = el.selectionEnd = sebelum.length;
                                                this.cocok = [];
                                            },
                                         }">
                                        <textarea id="komentar-baru" x-ref="komentar" wire:model="komentarBaru" rows="2"
                                                  placeholder="Tulis komentar… ketik @ untuk menyebut rekan"
                                                  x-on:input="cari()" x-on:keydown.escape.stop="cocok = []" x-on:blur="setTimeout(() => cocok = [], 150)"
                                                  class="block w-full rounded-lg border-line bg-white text-sm focus:border-brand focus:ring-brand/30"></textarea>
                                        <ul x-show="cocok.length" x-cloak
                                            class="absolute z-20 mt-1 w-56 overflow-hidden rounded-lg border border-line bg-card py-1 text-sm shadow-lg">
                                            <template x-for="nama in cocok" :key="nama">
                                                <li>
                                                    <button type="button" x-on:click="pilih(nama)"
                                                            class="block w-full px-3 py-2 text-left hover:bg-page" x-text="'@' + nama"></button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <p class="mt-1 text-[11px] text-ink-muted">Format: {{ \App\Support\Kanban\Teks::BANTUAN }}</p>
                                    @error('komentarBaru')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                    <button type="submit" class="mt-2 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Kirim</button>
                                </div>
                            </form>
                        @endif

                        <ol class="mt-4 space-y-4 px-2">
                            @forelse ($this->riwayat as $r)
                                @php $d = $r['data']; @endphp
                                @if ($r['jenis'] === 'komentar')
                                    <li wire:key="km-{{ $d->id }}" class="flex gap-2">
                                        <x-avatar :name="$d->penulis?->nama ?? $d->penulis?->name ?? '?'" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm"><span class="font-semibold">{{ $d->penulis?->nama ?? $d->penulis?->name ?? 'Pengguna dihapus' }}</span>
                                                <span class="text-xs text-ink-muted">{{ $d->created_at?->diffForHumans() }}@if ($d->diubah_at) (diubah)@endif</span></p>
                                            @if ($ubahKomentarId === $d->id)
                                                <form wire:submit="simpanKomentar" class="mt-1">
                                                    <textarea wire:model="isiKomentar" rows="3" aria-label="Ubah komentar"
                                                              class="block w-full rounded-lg border-line bg-white text-sm focus:border-brand focus:ring-brand/30"></textarea>
                                                    <div class="mt-1 flex gap-2">
                                                        <button type="submit" class="h-8 rounded-md bg-navy px-3 text-xs font-medium text-white">Simpan</button>
                                                        <button type="button" wire:click="$set('ubahKomentarId', null)" class="h-8 rounded-md px-3 text-xs hover:bg-[#DCDFE4]">Batal</button>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="isi-teks mt-1 break-words rounded-lg bg-white px-3 py-2 text-sm shadow-[0_1px_1px_rgba(9,30,66,.2)]">{!! \App\Support\Kanban\Teks::html($d->isi) !!}</div>

                                                @php $reaksi = $d->reaksi->groupBy('emoji'); @endphp
                                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                                    @foreach ($reaksi as $emoji => $daftar)
                                                        @php $sayaBereaksi = $daftar->contains('user_id', auth()->id()); @endphp
                                                        <button type="button" @disabled(! $ubah) wire:click="toggleReaksi({{ $d->id }}, '{{ $emoji }}')"
                                                                title="{{ $daftar->map(fn ($r) => $r->pemberi?->nama ?? $r->pemberi?->name)->filter()->join(', ') }}"
                                                                @class([
                                                                    'inline-flex h-6 items-center gap-1 rounded-full border px-2 text-xs',
                                                                    'border-navy bg-navy/10 text-navy' => $sayaBereaksi,
                                                                    'border-line bg-white text-ink-muted hover:bg-page' => ! $sayaBereaksi,
                                                                ])>{{ $emoji }} {{ $daftar->count() }}</button>
                                                    @endforeach

                                                    @if ($ubah)
                                                        <div class="relative" x-data="{ buka: false }">
                                                            <button type="button" x-on:click="buka = ! buka" aria-label="Beri reaksi"
                                                                    class="inline-flex h-6 items-center rounded-full border border-line bg-white px-2 text-xs text-ink-muted hover:bg-page">☺+</button>
                                                            <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu
                                                                 class="absolute left-0 z-20 mt-1 flex gap-1 rounded-xl border border-line bg-card p-1.5 shadow-lg">
                                                                @foreach (\App\Models\Kanban\Reaksi::PILIHAN as $pilihan)
                                                                    <button type="button" x-on:click="buka = false" wire:click="toggleReaksi({{ $d->id }}, '{{ $pilihan }}')"
                                                                            aria-label="Reaksi {{ $pilihan }}"
                                                                            class="flex h-8 w-8 items-center justify-center rounded-md text-base hover:bg-page">{{ $pilihan }}</button>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($ubah)
                                                    <div class="mt-1 flex gap-3 text-xs text-ink-muted">
                                                        @if ((int) $d->user_id === (int) auth()->id())
                                                            <button type="button" wire:click="mulaiUbahKomentar({{ $d->id }})" class="underline hover:text-ink">Ubah</button>
                                                        @endif
                                                        @if ((int) $d->user_id === (int) auth()->id() || $this->admin)
                                                            <button type="button" wire:click="hapusKomentar({{ $d->id }})" wire:confirm="Hapus komentar ini?" class="underline hover:text-[#AE2E24]">Hapus</button>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </li>
                                @else
                                    <li wire:key="ak-{{ $d->id }}" class="flex gap-2 text-sm">
                                        <x-avatar :name="$d->pelaku?->nama ?? $d->pelaku?->name ?? 'Sistem'" size="sm" />
                                        <div class="min-w-0">
                                            <p><span class="font-semibold">{{ $d->pelaku?->nama ?? $d->pelaku?->name ?? 'Sistem' }}</span>
                                                {{ $d->kalimat() }} @if ($d->keterangan)<span class="text-ink-muted">{{ $d->keterangan }}</span>@endif</p>
                                            <p class="text-xs text-ink-muted">{{ $d->created_at?->diffForHumans() }}</p>
                                        </div>
                                    </li>
                                @endif
                            @empty
                                <li class="text-sm text-ink-muted">Belum ada komentar atau aktivitas.</li>
                            @endforelse
                        </ol>
                    </section>
                </div>

                {{-- Kolom aksi --}}
                @if ($ubah)
                    <aside class="space-y-4">
                        <div class="space-y-1.5">
                            <h3 class="text-xs font-medium text-ink-muted">Tambahkan ke kartu</h3>

                            {{-- Anggota --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Anggota</button>
                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                     x-data="{ cari: '' }">
                                    <h4 class="text-center font-semibold">Anggota</h4>
                                    <input type="search" x-model="cari" placeholder="Cari staf…" aria-label="Cari staf"
                                           class="mt-2 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                    <ul class="mt-2 max-h-64 overflow-y-auto">
                                        @foreach ($this->calonAnggota as $u)
                                            @php $nm = $u->nama ?? $u->name; $pilih = $k->anggota->contains('id', $u->id); @endphp
                                            <li wire:key="ca-{{ $u->id }}" x-show="@js(mb_strtolower($nm)).includes(cari.toLowerCase())">
                                                <button type="button" wire:click="toggleAnggota({{ $u->id }})" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
                                                        class="flex min-h-[40px] w-full items-center gap-2 rounded-md px-2 text-left hover:bg-page">
                                                    <x-avatar :name="$nm" size="sm" />
                                                    <span class="min-w-0 flex-1 truncate">{{ $nm }}</span>
                                                    @if ($pilih)<span class="text-navy" aria-hidden="true">✓</span>@endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>

                            {{-- Label --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Label</button>
                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}">
                                    <h4 class="text-center font-semibold">Label</h4>
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($this->labelBoard as $l)
                                            @php $pilih = $k->label->contains('id', $l->id); @endphp
                                            <li wire:key="pl-{{ $l->id }}">
                                                <label class="flex min-h-[36px] cursor-pointer items-center gap-2">
                                                    <input type="checkbox" @checked($pilih) wire:click="toggleLabel({{ $l->id }})" class="h-4 w-4 rounded text-navy focus:ring-brand/30">
                                                    <span class="h-8 flex-1 truncate rounded px-3 text-sm font-medium leading-8 {{ Warna::label($l->warna) }}">{{ $l->nama }}</span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <form wire:submit="buatLabel" class="mt-3 border-t border-line pt-3">
                                        <input type="text" wire:model="namaLabelBaru" placeholder="Label baru (boleh kosong)" aria-label="Nama label baru"
                                               class="block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        <div class="mt-2 grid grid-cols-5 gap-1">
                                            @foreach (Warna::LABEL as $w => [$t, $latar])
                                                <label>
                                                    <input type="radio" wire:model="warnaLabelBaru" value="{{ $w }}" class="peer sr-only">
                                                    <span title="{{ $t }}" class="block h-6 cursor-pointer rounded {{ $latar }} ring-offset-1 peer-checked:ring-2 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand"></span>
                                                    <span class="sr-only">{{ $t }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <button type="submit" class="mt-2 h-8 w-full rounded-md bg-navy text-xs font-medium text-white">Buat & pasang</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Checklist --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Checklist</button>
                                <form x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                      wire:submit="tambahChecklist" x-on:submit="buka = false">
                                    <h4 class="text-center font-semibold">Tambah checklist</h4>
                                    <label for="judul-checklist" class="mt-2 block text-xs font-medium text-ink-muted">Judul</label>
                                    <input id="judul-checklist" type="text" wire:model="judulChecklist"
                                           class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                    <button type="submit" class="mt-2 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white">Tambah</button>
                                </form>
                            </div>

                            {{-- Tanggal --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Tanggal</button>
                                <form x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                      wire:submit="simpanTanggal" x-on:submit="buka = false">
                                    <h4 class="text-center font-semibold">Tanggal</h4>
                                    <label for="tgl-mulai" class="mt-2 block text-xs font-medium text-ink-muted">Mulai</label>
                                    <input id="tgl-mulai" type="date" wire:model="mulai"
                                           class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                    <label for="tgl-tenggat" class="mt-2 block text-xs font-medium text-ink-muted">Tenggat</label>
                                    <input id="tgl-tenggat" type="datetime-local" wire:model="tenggat"
                                           class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                    <div class="mt-3 flex gap-2">
                                        <button type="submit" class="h-9 flex-1 rounded-md bg-navy px-3 text-sm font-medium text-white">Simpan</button>
                                        <button type="button" wire:click="hapusTanggal" x-on:click="buka = false" class="h-9 rounded-md bg-[#E9EBEE] px-3 text-sm">Hapus</button>
                                    </div>
                                </form>
                            </div>

                            {{-- Lampiran --}}
                            <div>
                                <label class="{{ $tombol }} cursor-pointer focus-within:ring-2 focus-within:ring-brand">
                                    <span wire:loading.remove wire:target="berkas">Lampiran</span>
                                    <span wire:loading wire:target="berkas">Mengunggah…</span>
                                    <input type="file" wire:model="berkas" multiple class="sr-only">
                                </label>
                                <p class="mt-1 text-[11px] text-ink-muted">Bisa juga seret berkas ke kartu, atau tempel gambar (Ctrl+V).</p>

                                <div class="relative mt-1.5" x-data="{ buka: false }">
                                    <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Lampirkan tautan</button>
                                    <form x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                          wire:submit="tambahTautan" x-on:submit="buka = false">
                                        <h4 class="text-center font-semibold">Lampirkan tautan</h4>
                                        <label for="tautan-url" class="mt-2 block text-xs font-medium text-ink-muted">Alamat</label>
                                        <input id="tautan-url" type="url" wire:model="tautanUrl" placeholder="https://drive.google.com/…"
                                               class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        @error('tautanUrl')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                        <label for="tautan-nama" class="mt-2 block text-xs font-medium text-ink-muted">Nama tampilan (opsional)</label>
                                        <input id="tautan-nama" type="text" wire:model="tautanNama" placeholder="mis. Folder hasil edit"
                                               class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white">Lampirkan</button>
                                    </form>
                                </div>
                                @error('berkas')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                @error('berkas.*')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                            </div>

                            {{-- Sampul --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Sampul</button>
                                <div x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}">
                                    <h4 class="text-center font-semibold">Sampul</h4>
                                    <div class="mt-2 grid grid-cols-5 gap-1.5">
                                        @foreach (Warna::LABEL as $w => [$t, $latar])
                                            <button type="button" wire:click="warnaSampul('{{ $w }}')" title="{{ $t }}" aria-label="Sampul {{ $t }}"
                                                    class="h-8 rounded {{ $latar }} {{ $k->cover_warna === $w ? 'ring-2 ring-ink ring-offset-1' : '' }}"></button>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 text-xs text-ink-muted">Gambar dari lampiran juga bisa jadi sampul.</p>
                                    @if ($k->cover_warna || $k->cover_lampiran_id)
                                        <button type="button" wire:click="warnaSampul(null)" class="mt-2 h-8 w-full rounded-md bg-[#E9EBEE] text-xs font-medium">Lepas sampul</button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <h3 class="text-xs font-medium text-ink-muted">Tindakan</h3>

                            {{-- Pindahkan --}}
                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Pindahkan</button>
                                <form x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                      wire:submit="pindahkan" x-on:submit="buka = false">
                                    <h4 class="text-center font-semibold">Pindahkan kartu</h4>
                                    <label for="pindah-board" class="mt-2 block text-xs font-medium text-ink-muted">Board</label>
                                    <select id="pindah-board" wire:model.live="pindahBoard"
                                            class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        @foreach ($this->boardTujuan as $b)
                                            <option value="{{ $b->id }}">{{ $b->nama }}</option>
                                        @endforeach
                                    </select>
                                    <div class="mt-2 grid grid-cols-[minmax(0,1fr)_5rem] gap-2">
                                        <div>
                                            <label for="pindah-list" class="block text-xs font-medium text-ink-muted">List</label>
                                            <select id="pindah-list" wire:model.live="pindahKolom"
                                                    class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                                @forelse ($this->kolomTujuan as $kol)
                                                    <option value="{{ $kol->id }}">{{ $kol->nama }}</option>
                                                @empty
                                                    <option value="">Belum ada list</option>
                                                @endforelse
                                            </select>
                                        </div>
                                        <div>
                                            @php
                                                $isi = (int) ($this->kolomTujuan->firstWhere('id', $pindahKolom)?->kartu_count ?? 0);
                                                $maks = (int) $pindahKolom === (int) $k->kolom_id && ! $k->diarsipkan_at ? $isi : $isi + 1;
                                            @endphp
                                            <label for="pindah-urutan" class="block text-xs font-medium text-ink-muted">Urutan</label>
                                            <select id="pindah-urutan" wire:model="pindahUrutan"
                                                    class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                                @for ($i = 1; $i <= max(1, $maks); $i++)
                                                    <option value="{{ $i }}">{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                    </div>
                                    @error('pindahKolom')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror
                                    <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white">Pindahkan</button>
                                </form>
                            </div>

                            <div class="relative" x-data="{ buka: false }">
                                <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka" class="{{ $tombol }}">Salin</button>
                                <form x-show="buka" x-cloak x-on:click.outside="buka = false" x-on:keydown.escape.stop="buka = false" data-panel-kartu class="{{ $panel }}"
                                      wire:submit="salin" x-on:submit="buka = false">
                                    <h4 class="text-center font-semibold">Salin kartu</h4>
                                    <label for="judul-salinan" class="mt-2 block text-xs font-medium text-ink-muted">Judul</label>
                                    <textarea id="judul-salinan" wire:model="judulSalinan" rows="2"
                                              class="mt-1 block w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30"></textarea>
                                    @error('judulSalinan')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror

                                    <label for="salin-kolom" class="mt-2 block text-xs font-medium text-ink-muted">List tujuan</label>
                                    <select id="salin-kolom" wire:model="salinKolom"
                                            class="mt-1 block min-h-[36px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                                        @foreach ($this->kolomTujuan as $kol)
                                            <option value="{{ $kol->id }}">{{ $kol->nama }}</option>
                                        @endforeach
                                    </select>
                                    @error('salinKolom')<p class="mt-1 text-xs text-[#AE2E24]">{{ $message }}</p>@enderror

                                    <fieldset class="mt-3">
                                        <legend class="text-xs font-medium text-ink-muted">Ikut disalin</legend>
                                        @foreach (['label' => 'Label', 'anggota' => 'Anggota', 'checklist' => 'Checklist', 'lampiran' => 'Lampiran'] as $kunci => $labelBawa)
                                            <label class="mt-1 flex min-h-[32px] items-center gap-2">
                                                <input type="checkbox" wire:model="bawaSalinan" value="{{ $kunci }}" class="rounded text-brand focus:ring-brand/30">
                                                {{ $labelBawa }}
                                            </label>
                                        @endforeach
                                    </fieldset>
                                    <button type="submit" class="mt-3 h-9 rounded-md bg-navy px-3 text-sm font-medium text-white">Buat salinan</button>
                                </form>
                            </div>

                            @unless ($k->order_id)
                                <button type="button" wire:click="toggleTemplat" class="{{ $tombol }}" aria-pressed="{{ $k->templat ? 'true' : 'false' }}">
                                    {{ $k->templat ? 'Bukan templat lagi' : 'Jadikan templat' }}
                                    @if ($k->templat)<span class="ml-auto text-navy" aria-hidden="true">✓</span>@endif
                                </button>
                            @endunless

                            <button type="button" wire:click="toggleIkut" class="{{ $tombol }}" aria-pressed="{{ $this->mengikuti ? 'true' : 'false' }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" /><circle cx="12" cy="12" r="2.5" /></svg>
                                {{ $this->mengikuti ? 'Berhenti ikuti' : 'Ikuti' }}
                                @if ($this->mengikuti)<span class="ml-auto text-navy" aria-hidden="true">✓</span>@endif
                            </button>
                            <button type="button" wire:click="arsipkan" class="{{ $tombol }}">Arsipkan</button>
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </div>
</div>
