@php
    use App\Support\Kanban\Warna;

    $d = $this->dasbor;
    $maks = fn ($daftar, $kunci = 'jml') => max(1, collect($daftar)->max($kunci) ?? 1);
@endphp

{{-- Dasbor: ringkasan isi board, memakai penyaring yang sedang aktif. --}}
<div class="min-h-0 flex-1 overflow-auto p-3 pb-16 sm:px-4">
    <div class="mx-auto w-full max-w-5xl space-y-4">

        {{-- Angka ringkas --}}
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-line sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['Active cards', $d['total'], 'text-ink'],
                ['Overdue', $d['lewat'], 'text-[#C9372C]'],
                ['Due in 7 days', $d['pekanIni'], 'text-[#B8620A]'],
                ['Due date done', $d['selesai'], 'text-[#1F845A]'],
                ['No due date', $d['tanpaTenggat'], 'text-ink-muted'],
                ['No members', $d['tanpaAnggota'], 'text-ink-muted'],
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
                <h2 class="text-sm font-semibold text-ink">Cards per list</h2>
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
                        <li class="text-sm text-ink-muted">This board has no lists yet.</li>
                    @endforelse
                </ul>
            </section>

            {{-- Per anggota --}}
            <section class="rounded-xl bg-card p-4">
                <h2 class="text-sm font-semibold text-ink">Cards per member</h2>
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
                        <li class="text-sm text-ink-muted">No cards assigned yet.</li>
                    @endforelse
                </ul>
                @if ($d['tanpaAnggota'])
                    <p class="mt-3 text-xs text-ink-muted">{{ $d['tanpaAnggota'] }} kartu belum ada penanggung jawabnya.</p>
                @endif
            </section>

            {{-- Per label --}}
            <section class="rounded-xl bg-card p-4 lg:col-span-2">
                <h2 class="text-sm font-semibold text-ink">Cards per label</h2>
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
                        <li class="text-sm text-ink-muted">No labelled cards yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <p class="px-1 text-xs text-white/90">
            The numbers follow the active filter. Clear the filter to see the whole board.
        </p>
    </div>
</div>
