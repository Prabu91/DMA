<div x-data="{ seret: null, atas: null }"
     x-init="setInterval(() => { if (! seret && ! document.hidden && ! $wire.kartuId) $wire.$refresh() }, 30000)">
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Papan order'],
    ]" />

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">
                Papan order
                @if ($mode === 'orang')<span class="text-ink-muted">· {{ \App\Support\TahapOrder::nama($tahapDipilih) }}</span>@endif
            </h1>
            <p class="text-sm text-ink-muted">Kartu dibuat otomatis dari order. Seret kartu, atau pakai tombol <span class="font-medium text-ink">Pindah</span>.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded-lg border border-line bg-card px-2.5 py-1">{{ $this->ringkasan['total'] }} kartu berjalan</span>
            @if ($this->ringkasan['lewat'])
                <span class="rounded-lg bg-status-danger/10 px-2.5 py-1 font-medium text-status-danger">{{ $this->ringkasan['lewat'] }} lewat tenggat</span>
            @endif
            @if ($this->ringkasan['tertahan'])
                <span class="rounded-lg bg-status-pending/10 px-2.5 py-1 font-medium text-status-pending">{{ $this->ringkasan['tertahan'] }} tertahan</span>
            @endif
        </div>
    </div>

    {{-- Alat --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div role="group" aria-label="Kelompokkan kolom" class="flex rounded-lg bg-line/60 p-1">
            <button type="button" wire:click="$set('mode', 'tahap')" aria-pressed="{{ $mode === 'tahap' ? 'true' : 'false' }}"
                    @class(['min-h-[36px] rounded-md px-3 text-sm', 'bg-card font-medium text-ink shadow-sm' => $mode === 'tahap', 'text-ink-muted hover:text-ink' => $mode !== 'tahap'])>Per tahap</button>
            <button type="button" wire:click="$set('mode', 'orang')" aria-pressed="{{ $mode === 'orang' ? 'true' : 'false' }}"
                    @class(['min-h-[36px] rounded-md px-3 text-sm', 'bg-card font-medium text-ink shadow-sm' => $mode === 'orang', 'text-ink-muted hover:text-ink' => $mode !== 'orang'])>Per penanggung jawab</button>
        </div>

        @if ($mode === 'orang')
            <label class="sr-only" for="papan-tahap">Tahap</label>
            <select id="papan-tahap" wire:model.live="tahapDipilih"
                    class="min-h-[40px] rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
                @foreach (\App\Support\TahapOrder::pilihan() as $h => $label)
                    <option value="{{ $h }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif

        <label class="sr-only" for="papan-jalur">Jalur</label>
        <select id="papan-jalur" wire:model.live="jalur"
                class="min-h-[40px] rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
            <option value="">Semua jalur</option>
            @foreach ($this->jalurPilihan as $g => $label)
                <option value="{{ $g }}">{{ $label }}</option>
            @endforeach
        </select>

        <button type="button" wire:click="$toggle('tugasSaya')" aria-pressed="{{ $tugasSaya ? 'true' : 'false' }}"
                @class(['min-h-[40px] rounded-lg border px-3 text-sm', 'border-brand bg-brand/10 font-medium text-ink' => $tugasSaya, 'border-line bg-card text-ink hover:bg-page' => ! $tugasSaya])>Tugas saya</button>
        <button type="button" wire:click="$toggle('tertahanSaja')" aria-pressed="{{ $tertahanSaja ? 'true' : 'false' }}"
                @class(['min-h-[40px] rounded-lg border px-3 text-sm', 'border-status-pending bg-status-pending/10 font-medium text-ink' => $tertahanSaja, 'border-line bg-card text-ink hover:bg-page' => ! $tertahanSaja])>Tertahan saja</button>

        <div class="w-full sm:ml-auto sm:w-64">
            <x-input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari sekolah / kode booking" aria-label="Cari kartu" />
        </div>
    </div>

    @if ($pesan)
        <div class="mb-3 rounded-lg border border-status-success/20 bg-status-success/10 px-3 py-2 text-sm text-status-success" role="status">{{ $pesan }}</div>
    @endif
    @if ($galat && ! $kartuId)
        <div class="mb-3 rounded-lg border border-status-danger/20 bg-status-danger/10 px-3 py-2 text-sm text-status-danger" role="alert">{{ $galat }}</div>
    @endif

    {{-- Papan --}}
    <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0">
        @foreach ($this->kolom as $kol)
            <section wire:key="kol-{{ $kol['kunci'] }}"
                     aria-label="{{ $kol['judul'] }}"
                     @if ($kol['bisaDrop'])
                         x-on:dragover.prevent="atas = @js($kol['kunci'])"
                         x-on:dragleave.self="atas = null"
                         x-on:drop.prevent="if (seret) { $wire.pindah(seret, @js($kol['kunci'])) } seret = null; atas = null"
                     @endif
                     :class="atas === @js($kol['kunci']) ? 'ring-2 ring-brand' : ''"
                     class="flex w-[85vw] max-w-[20rem] shrink-0 snap-start flex-col rounded-xl bg-line/40 p-2.5 sm:w-72">
                <header class="mb-2 flex items-start justify-between gap-2 px-1">
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($kol['inisial'])
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy/10 text-[11px] font-semibold text-navy">{{ $kol['inisial'] }}</span>
                        @endif
                        <div class="min-w-0">
                            @if ($kol['huruf'])<div class="text-[11px] font-semibold text-navy">{{ $kol['huruf'] }}</div>@endif
                            <h2 class="truncate text-sm font-medium text-ink">{{ $kol['judul'] }}</h2>
                            <p class="truncate text-xs text-ink-muted">{{ $kol['ket'] }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-card px-2 py-0.5 text-xs font-medium text-ink">{{ $kol['total'] }}</span>
                </header>

                <div class="flex max-h-[calc(100vh-18rem)] min-h-[6rem] flex-col gap-2 overflow-y-auto">
                    @forelse ($kol['kartu'] as $k)
                        <article wire:key="kartu-{{ $kol['kunci'] }}-{{ $k['id'] }}"
                                 @if ($k['bisaKelola'])
                                     draggable="true"
                                     x-on:dragstart="seret = {{ $k['id'] }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $k['id'] }}')"
                                     x-on:dragend="seret = null; atas = null"
                                 @endif
                                 :class="seret === {{ $k['id'] }} ? 'opacity-50' : ''"
                                 @class([
                                     'rounded-xl border bg-card p-3 shadow-sm',
                                     'border-status-danger/40' => $k['tenggatKeadaan'] === 'lewat',
                                     'border-line' => $k['tenggatKeadaan'] !== 'lewat',
                                     'cursor-grab active:cursor-grabbing' => $k['bisaKelola'],
                                 ])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate font-mono text-[11px] text-ink-muted">{{ $k['kode'] }}</span>
                                @if ($k['susulan'])<x-badge variant="navy">Susulan</x-badge>@endif
                            </div>
                            <a href="{{ $k['url'] }}" wire:navigate class="mt-1 block text-sm font-medium leading-snug text-ink hover:text-brand-hover">{{ $k['sekolah'] }}</a>
                            <p class="mt-0.5 text-xs text-ink-muted">Event {{ $k['event'] }} · {{ $k['marketing'] }}</p>

                            @if ($k['tertahan'])
                                <p class="mt-2 rounded-lg bg-status-pending/10 px-2 py-1.5 text-xs text-ink">
                                    <span class="font-medium text-status-pending">Tertahan:</span> {{ $k['tertahan'] }}
                                </p>
                            @endif

                            @if ($k['jalur'])
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($k['jalur'] as $j)
                                        <span class="rounded-md bg-page px-1.5 py-0.5 text-[11px] text-ink-muted">{{ $j }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($k['tenggat'])
                                        <span @class([
                                            'rounded-md px-1.5 py-0.5 text-[11px]',
                                            'bg-status-danger/10 font-medium text-status-danger' => $k['tenggatKeadaan'] === 'lewat',
                                            'bg-status-pending/10 font-medium text-status-pending' => $k['tenggatKeadaan'] === 'hari',
                                            'bg-page text-ink-muted' => $k['tenggatKeadaan'] === 'aman',
                                        ])>{{ $k['tenggat'] }}</span>
                                    @endif
                                    @if ($k['qc'])
                                        <span @class([
                                            'text-[11px] font-medium',
                                            'text-status-success' => $k['qcLengkap'],
                                            'text-status-pending' => ! $k['qcLengkap'],
                                        ]) title="{{ $k['qcLabel'] }}">{{ $k['qcLengkap'] ? '✓' : '○' }} {{ $k['qc'] }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if ($k['pj'])
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy/10 text-[10px] font-semibold text-navy" title="{{ $k['pj'] }}">{{ $k['inisialPj'] }}</span>
                                    @endif
                                    @if ($k['bisaKelola'])
                                        <button type="button" wire:click="bukaKartu({{ $k['id'] }})"
                                                class="min-h-[32px] rounded-md border border-line px-2 text-xs font-medium text-ink hover:border-brand/50">Pindah</button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="px-1 py-6 text-center text-xs text-ink-muted">Kosong</p>
                    @endforelse

                    @if ($kol['total'] > count($kol['kartu']))
                        <p class="px-1 py-2 text-center text-xs text-ink-muted">+{{ $kol['total'] - count($kol['kartu']) }} kartu lainnya — persempit dengan filter</p>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    {{-- Lembar kartu: pindah tahap, penanggung jawab, tertahan, tenggat --}}
    @if ($kartuId && $this->kartuTerbuka)
        @php
            $o = $this->kartuTerbuka;
            $pil = $this->pilihanKartu;
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="lembar-kartu"
             x-on:keydown.escape.window="$wire.tutupKartu()">
            <div class="absolute inset-0 bg-ink/40" wire:click="tutupKartu"></div>
            <div role="dialog" aria-modal="true" aria-labelledby="judul-kartu"
                 class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-2xl border border-line bg-card p-5 shadow-lg sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="judul-kartu" class="text-base font-medium text-ink">{{ $o->sekolah?->nama }}</h2>
                        <p class="text-xs text-ink-muted">
                            <span class="font-mono">{{ $o->booking_code ?? 'Order #'.$o->id }}</span>
                            · {{ \App\Support\TahapOrder::label($o->tahap) }}
                            @if ($o->pjPapan) · {{ $o->pjPapan->nama ?? $o->pjPapan->name }}@endif
                        </p>
                    </div>
                    <button type="button" wire:click="tutupKartu" aria-label="Tutup"
                            class="-mr-2 -mt-2 flex h-11 w-11 items-center justify-center rounded-lg text-ink-muted hover:bg-page hover:text-ink">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if ($galat)
                    <p class="mt-3 rounded-lg border border-status-danger/20 bg-status-danger/10 px-3 py-2 text-sm text-status-danger" role="alert">{{ $galat }}</p>
                @endif

                <div class="mt-4 space-y-5">
                    @if ($pil['tahap'])
                        <div class="space-y-2">
                            <label for="tujuan-tahap" class="block text-sm font-medium text-ink">Pindah ke tahap</label>
                            <div class="flex gap-2">
                                <select id="tujuan-tahap" wire:model="tujuanTahap"
                                        class="min-h-[44px] min-w-0 flex-1 rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
                                    @foreach ($pil['tahap'] as $h => $label)
                                        <option value="{{ $h }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-button wire:click="simpanTahap">Pindahkan</x-button>
                            </div>
                        </div>
                    @endif

                    @if ($pil['pj'])
                        <div class="space-y-2">
                            <label for="tujuan-pj" class="block text-sm font-medium text-ink">Penanggung jawab</label>
                            <div class="flex gap-2">
                                <select id="tujuan-pj" wire:model="tujuanPj"
                                        class="min-h-[44px] min-w-0 flex-1 rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
                                    <option value="">— Belum ditugaskan —</option>
                                    @foreach ($pil['pj'] as $id => $nama)
                                        <option value="{{ $id }}">{{ $nama }}</option>
                                    @endforeach
                                </select>
                                <x-button variant="secondary" wire:click="simpanPj">Simpan</x-button>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-2">
                        <label for="alasan-tahan" class="block text-sm font-medium text-ink">Tertahan karena</label>
                        <textarea id="alasan-tahan" wire:model="alasanTahan" rows="2" maxlength="255"
                                  placeholder="mis. menunggu kode desain dari marketing"
                                  class="block w-full rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <x-button variant="secondary" wire:click="simpanTahan">Tandai tertahan</x-button>
                            @if ($o->tertahan_alasan)
                                <x-button variant="ghost" wire:click="lanjutkan">Sudah tidak tertahan</x-button>
                            @endif
                        </div>
                    </div>

                    @if ($pil['pengelola'])
                        <div class="space-y-2">
                            <label for="tenggat-baru" class="block text-sm font-medium text-ink">Tenggat khusus</label>
                            <div class="flex gap-2">
                                <input id="tenggat-baru" type="date" wire:model="tenggatBaru"
                                       class="min-h-[44px] min-w-0 flex-1 rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30">
                                <x-button variant="secondary" wire:click="simpanTenggat">Simpan</x-button>
                            </div>
                            <p class="text-xs text-ink-muted">Kosongkan lalu simpan untuk kembali ke tenggat otomatis dari tanggal event.</p>
                        </div>
                    @endif

                    <a href="{{ route('app.order.show', $o->id) }}" wire:navigate class="block text-sm font-medium text-brand hover:text-brand-hover">Buka detail order →</a>
                </div>
            </div>
        </div>
    @endif
</div>
