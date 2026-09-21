@php
    use App\Support\Kanban\Warna;

    $bulanAktif = $this->bulanAktif;
    $mulai = $bulanAktif->copy()->startOfWeek();
    $akhir = $bulanAktif->copy()->endOfMonth()->endOfWeek();
    $kalender = $this->kalender;

    $namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $namaHari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
@endphp

{{-- Tampilan kalender: kartu diletakkan pada tanggal tenggatnya. --}}
<div class="min-h-0 flex-1 overflow-auto p-3 sm:px-4">
    <div class="mb-3 flex flex-wrap items-center gap-2 text-white">
        <button type="button" wire:click="geserBulan(-1)" aria-label="Bulan sebelumnya"
                class="flex h-9 w-9 items-center justify-center rounded-md bg-white/15 hover:bg-white/25">‹</button>
        <h2 class="min-w-[10rem] text-center text-base font-semibold">
            {{ $namaBulan[$bulanAktif->month - 1] }} {{ $bulanAktif->year }}
        </h2>
        <button type="button" wire:click="geserBulan(1)" aria-label="Bulan berikutnya"
                class="flex h-9 w-9 items-center justify-center rounded-md bg-white/15 hover:bg-white/25">›</button>
        <button type="button" wire:click="bulanIni" class="h-9 rounded-md bg-white/15 px-3 text-sm hover:bg-white/25">Bulan ini</button>
        @if ($this->bolehUbah)
            <span class="hidden text-xs text-white/80 sm:inline">Seret kartu ke tanggal lain untuk mengganti tenggat.</span>
        @endif
        @if ($this->tanpaTenggat)
            <span class="ml-auto rounded-md bg-white/15 px-2.5 py-1.5 text-xs">{{ $this->tanpaTenggat }} kartu tanpa tenggat (tidak tampil di kalender)</span>
        @endif
    </div>

    {{-- Di layar kecil kalender digeser ke samping supaya kotak harinya tetap terbaca. --}}
    <div class="min-w-[44rem] overflow-hidden rounded-xl bg-card">
        <div class="grid grid-cols-7 border-b border-line bg-[#F1F2F4] text-center text-xs font-medium text-ink-muted">
            @foreach ($namaHari as $hari)
                <div class="px-1 py-2">{{ $hari }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @php $tanggal = $mulai->copy(); @endphp
            @while ($tanggal <= $akhir)
                @php
                    $kunci = $tanggal->format('Y-m-d');
                    $kartuHari = $kalender->get($kunci, collect());
                    $bulanLain = $tanggal->month !== $bulanAktif->month;
                @endphp
                <div wire:key="hari-{{ $kunci }}"
                     @class([
                        'min-h-[6.5rem] border-b border-r border-line p-1.5 last:border-r-0',
                        'bg-page/60' => $bulanLain,
                     ])>
                    <div class="mb-1 flex items-center justify-between">
                        <span @class([
                            'flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1 text-xs',
                            'bg-brand font-semibold text-ink' => $tanggal->isToday(),
                            'text-ink-muted' => ! $tanggal->isToday() && $bulanLain,
                            'text-ink' => ! $tanggal->isToday() && ! $bulanLain,
                        ])>{{ $tanggal->day }}</span>
                    </div>

                    <ul @if ($this->bolehUbah) wire:sort="ubahTenggatKalender" wire:sort:group="kalender" wire:sort:group-id="{{ $kunci }}" wire:sort:config="{ delay: 220, delayOnTouchOnly: true, touchStartThreshold: 6 }" @endif
                        class="min-h-[2.5rem] space-y-1" aria-label="Kartu bertenggat {{ $tanggal->format('d-m-Y') }}">
                        @foreach ($kartuHari as $kartu)
                            @php $keadaan = $kartu->keadaanTenggat(); @endphp
                            <li wire:key="kal-{{ $kartu->id }}" wire:sort:item="{{ $kartu->id }}">
                                <button type="button" wire:click="bukaKartu({{ $kartu->id }})"
                                        @class([
                                            'block w-full truncate rounded px-1.5 py-1 text-left text-[11px] hover:ring-2 hover:ring-brand/60',
                                            'bg-[#1F845A] text-white' => $keadaan === 'selesai',
                                            'bg-[#C9372C] text-white' => $keadaan === 'lewat',
                                            'bg-[#F5CD47] text-ink' => $keadaan === 'segera',
                                            'bg-[#E9EBEE] text-ink' => $keadaan === 'biasa',
                                        ])
                                        title="{{ $kartu->judul }} — {{ $kartu->kolom?->nama }}, {{ $kartu->tenggat_pada->format('H:i') }}">
                                    <span class="font-medium">{{ $kartu->tenggat_pada->format('H:i') }}</span> {{ $kartu->judul }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @php $tanggal->addDay(); @endphp
            @endwhile
        </div>
    </div>
</div>
