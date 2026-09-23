@php
    use App\Livewire\Kanban\PapanBoard;
    use App\Support\Kanban\Warna;

    $awal = $this->awalLinimasa;
    $hari = PapanBoard::HARI_LINIMASA;
    $akhir = $awal->copy()->addDays($hari - 1);
    $perKolom = $this->linimasa;
    $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    // Satu hari = 34px; lebar bidang tetap supaya batang mudah dihitung.
    $lebarHari = 34;
    $lebarBidang = $hari * $lebarHari;
@endphp

{{-- Linimasa: kartu bertanggal digambar sebagai batang, dikelompokkan per list. --}}
<div class="min-h-0 flex-1 overflow-auto p-3 pb-16 sm:px-4">
    <div class="mb-3 flex flex-wrap items-center gap-2 text-white">
        <button type="button" wire:click="geserLinimasa(-2)" aria-label="Back two weeks"
                class="flex h-9 w-9 items-center justify-center rounded-md bg-white/15 hover:bg-white/25">‹</button>
        <h2 class="min-w-[13rem] text-center text-sm font-semibold">
            {{ $awal->day }} {{ $namaBulan[$awal->month - 1] }} – {{ $akhir->day }} {{ $namaBulan[$akhir->month - 1] }} {{ $akhir->year }}
        </h2>
        <button type="button" wire:click="geserLinimasa(2)" aria-label="Forward two weeks"
                class="flex h-9 w-9 items-center justify-center rounded-md bg-white/15 hover:bg-white/25">›</button>
        <button type="button" wire:click="pekanIni" class="h-9 rounded-md bg-white/15 px-3 text-sm hover:bg-white/25">This week</button>
        <span class="ml-auto text-xs text-white/90">Only cards with dates show up here.</span>
    </div>

    <div class="overflow-x-auto rounded-xl bg-card">
        <div style="min-width: {{ $lebarBidang + 220 }}px">
            {{-- Kepala tanggal --}}
            <div class="flex border-b border-line bg-[#F1F2F4]">
                <div class="w-[220px] shrink-0 px-3 py-2 text-xs font-medium text-ink-muted">Cards</div>
                <div class="flex" style="width: {{ $lebarBidang }}px">
                    @for ($i = 0; $i < $hari; $i++)
                        @php $t = $awal->copy()->addDays($i); @endphp
                        <div @class([
                                'shrink-0 border-l border-line py-1 text-center text-[10px] leading-tight',
                                'bg-brand/20 font-semibold text-ink' => $t->isToday(),
                                'text-ink-muted' => ! $t->isToday(),
                             ])
                             style="width: {{ $lebarHari }}px">
                            <div>{{ $t->day }}</div>
                            <div>{{ $namaBulan[$t->month - 1] }}</div>
                        </div>
                    @endfor
                </div>
            </div>

            @forelse ($this->kolom as $kolom)
                @php $isi = $perKolom->get($kolom->id, collect()); @endphp
                @continue($isi->isEmpty())

                <div wire:key="lini-kolom-{{ $kolom->id }}" class="border-b border-line">
                    <div class="flex items-center gap-2 bg-page px-3 py-1.5 text-xs font-semibold text-ink">
                        @if ($kolom->warna)<span class="h-2 w-2 rounded-full {{ Warna::labelLatar($kolom->warna) }}"></span>@endif
                        {{ $kolom->nama }}
                        <span class="text-ink-muted">{{ $isi->count() }} kartu bertanggal</span>
                    </div>

                    @foreach ($isi as $kartu)
                        @php
                            $mulai = $kartu->mulai_pada ?? $kartu->tenggat_pada;
                            $selesai = $kartu->tenggat_pada ?? $kartu->mulai_pada;
                            $dariHari = max(0, $awal->diffInDays($mulai->copy()->startOfDay(), false));
                            $sampaiHari = min($hari - 1, $awal->diffInDays($selesai->copy()->startOfDay(), false));
                            $panjang = max(1, $sampaiHari - $dariHari + 1);
                            $keadaan = $kartu->keadaanTenggat();
                        @endphp
                        <div wire:key="lini-{{ $kartu->id }}" class="flex items-center hover:bg-page">
                            <button type="button" wire:click="bukaKartu({{ $kartu->id }})"
                                    class="w-[220px] shrink-0 truncate px-3 py-1.5 text-left text-sm hover:underline">
                                <span class="font-mono text-[11px] text-ink-muted">#{{ $kartu->id }}</span> {{ $kartu->judul }}
                            </button>
                            <div class="relative py-1.5" style="width: {{ $lebarBidang }}px">
                                <button type="button" wire:click="bukaKartu({{ $kartu->id }})"
                                        title="{{ $kartu->judul }} — {{ $mulai->translatedFormat('j M') }} s.d. {{ $selesai->translatedFormat('j M') }}"
                                        @class([
                                            'absolute top-1.5 h-6 truncate rounded px-2 text-left text-[11px] hover:ring-2 hover:ring-brand',
                                            'bg-[#1F845A] text-white' => $keadaan === 'selesai',
                                            'bg-[#C9372C] text-white' => $keadaan === 'lewat',
                                            'bg-[#F5CD47] text-ink' => $keadaan === 'segera',
                                            'bg-navy text-white' => ! in_array($keadaan, ['selesai', 'lewat', 'segera'], true),
                                        ])
                                        style="left: {{ $dariHari * $lebarHari }}px; width: {{ $panjang * $lebarHari - 4 }}px">
                                    {{ $kartu->judul }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="px-3 py-10 text-center text-sm text-ink-muted">No dated cards in this range.</p>
            @endforelse
        </div>
    </div>

    <p class="mt-2 px-1 text-xs text-white/90">
        Batang dibaca dari tanggal mulai sampai tenggat. Cards yang hanya punya salah satunya digambar satu hari.
        Change the dates inside the card or from the Calendar view.
    </p>
</div>
