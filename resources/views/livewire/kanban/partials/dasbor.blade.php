@php
    use App\Support\Kanban\Warna;

    $d = $this->dasbor;
    $maks = fn ($daftar, $kunci = 'jml') => max(1, collect($daftar)->max($kunci) ?? 1);
@endphp

{{-- Dasbor: ringkasan isi board, memakai penyaring yang sedang aktif. --}}
<div class="min-h-0 flex-1 overflow-auto p-3 sm:px-4">
    <div class="mx-auto w-full max-w-5xl space-y-4">

        {{-- Angka ringkas --}}
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-line sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['Kartu aktif', $d['total'], 'text-ink'],
                ['Lewat tenggat', $d['lewat'], 'text-[#C9372C]'],
                ['Tenggat 7 hari', $d['pekanIni'], 'text-[#B8620A]'],
                ['Tenggat selesai', $d['selesai'], 'text-[#1F845A]'],
                ['Tanpa tenggat', $d['tanpaTenggat'], 'text-ink-muted'],
                ['Tanpa anggota', $d['tanpaAnggota'], 'text-ink-muted'],
            ] as [$label, $angka, $warna])
                <div class="bg-card px-4 py-3">
                    <div class="text-2xl font-semibold tabular-nums {{ $warna }}">{{ $angka }}</div>
                    <div class="text-xs text-ink-muted">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Per list --}}
            <section class="rounded-xl bg-card p-4">
                <h2 class="text-sm font-semibold text-ink">Kartu per list</h2>
                <ul class="mt-3 space-y-2">
                    @forelse ($d['perKolom'] as $baris)
                        <li>
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate text-ink">{{ $baris['nama'] }}</span>
                                <span class="tabular-nums text-ink-muted">{{ $baris['jml'] }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-page">
                                <div class="h-full rounded-full bg-navy" style="width: {{ round($baris['jml'] / $maks($d['perKolom']) * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-ink-muted">Board ini belum punya list.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Per anggota --}}
            <section class="rounded-xl bg-card p-4">
                <h2 class="text-sm font-semibold text-ink">Kartu per anggota</h2>
                <ul class="mt-3 space-y-2">
                    @forelse ($d['perAnggota'] as $baris)
                        <li>
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="min-w-0 truncate text-ink">{{ $baris->nama }}</span>
                                <span class="tabular-nums text-ink-muted">{{ $baris->jml }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-page">
                                <div class="h-full rounded-full bg-brand" style="width: {{ round($baris->jml / $maks($d['perAnggota']) * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-ink-muted">Belum ada kartu yang ditugaskan.</li>
                    @endforelse
                </ul>
                @if ($d['tanpaAnggota'])
                    <p class="mt-3 text-xs text-ink-muted">{{ $d['tanpaAnggota'] }} kartu belum ada penanggung jawabnya.</p>
                @endif
            </section>

            {{-- Per label --}}
            <section class="rounded-xl bg-card p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold text-ink">Kartu per label</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @forelse ($d['perLabel'] as $baris)
                        <li class="flex items-center gap-2">
                            <span class="h-5 w-24 shrink-0 truncate rounded px-1.5 text-[11px] font-medium leading-5 {{ Warna::label($baris->warna) }}">{{ $baris->nama ?: Warna::namaLabel($baris->warna) }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-page">
                                <span class="block h-full rounded-full {{ Warna::labelLatar($baris->warna) }}" style="width: {{ round($baris->jml / $maks($d['perLabel']) * 100) }}%"></span>
                            </span>
                            <span class="w-8 text-right text-sm tabular-nums text-ink-muted">{{ $baris->jml }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-ink-muted">Belum ada kartu berlabel.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <p class="px-1 text-xs text-white/90">
            Angka mengikuti filter yang sedang aktif. Bersihkan filter untuk melihat seluruh board.
        </p>
    </div>
</div>
