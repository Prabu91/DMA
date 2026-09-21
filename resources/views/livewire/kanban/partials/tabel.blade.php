@php
    use App\Livewire\Kanban\PapanBoard;
    use App\Support\Kanban\Warna;
@endphp

{{-- Tampilan tabel: semua kartu board dalam satu daftar yang bisa diurutkan. --}}
<div class="min-h-0 flex-1 overflow-y-auto p-3 sm:px-4">
    {{-- Kotak cari khusus tabel; memakai penyaring yang sama dengan papan. --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative min-w-0 flex-1 sm:max-w-sm">
            <label for="cari-tabel" class="sr-only">Cari kartu di board ini</label>
            <input id="cari-tabel" type="search" wire:model.live.debounce.400ms="cari"
                   placeholder="Cari judul kartu di board ini…"
                   class="block min-h-[40px] w-full rounded-lg border-0 bg-white/95 text-sm text-ink placeholder:text-ink-muted focus:ring-2 focus:ring-brand">
        </div>
        @if ($this->adaSaringan)
            <button type="button" wire:click="bersihkanSaringan"
                    class="min-h-[40px] rounded-lg bg-white/20 px-3 text-sm text-white hover:bg-white/30">Bersihkan filter</button>
        @endif
        <span class="ml-auto text-xs text-white/90">{{ $this->baris->total() }} kartu</span>
    </div>

    {{-- overflow-x-auto (bukan hidden) supaya tabel bisa digeser ke samping di HP. --}}
    <div class="overflow-x-auto rounded-xl bg-card">
        <table class="w-full min-w-[46rem] border-collapse text-sm">
            <caption class="sr-only">Semua kartu di board {{ $board->nama }}</caption>
            <thead class="bg-[#F1F2F4] text-left text-xs text-ink-muted">
                <tr>
                    @foreach (['judul' => 'Kartu', 'list' => 'List'] as $kunci => $judulKolom)
                        <th scope="col" class="px-3 py-2 font-medium" aria-sort="{{ $urutTabel === $kunci ? ($arahTabel === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="urutkan('{{ $kunci }}')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ $judulKolom }}
                                @if ($urutTabel === $kunci)<span aria-hidden="true">{{ $arahTabel === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                    @endforeach
                    <th scope="col" class="px-3 py-2 font-medium">Label</th>
                    <th scope="col" class="px-3 py-2 font-medium">Anggota</th>
                    <th scope="col" class="px-3 py-2 font-medium" aria-sort="{{ $urutTabel === 'tenggat' ? ($arahTabel === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button type="button" wire:click="urutkan('tenggat')" class="inline-flex items-center gap-1 hover:text-ink">
                            Tenggat
                            @if ($urutTabel === 'tenggat')<span aria-hidden="true">{{ $arahTabel === 'asc' ? '↑' : '↓' }}</span>@endif
                        </button>
                    </th>
                    <th scope="col" class="px-3 py-2 font-medium">Checklist</th>
                    <th scope="col" class="px-3 py-2 font-medium" aria-sort="{{ $urutTabel === 'dibuat' ? ($arahTabel === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button type="button" wire:click="urutkan('dibuat')" class="inline-flex items-center gap-1 hover:text-ink">
                            Dibuat
                            @if ($urutTabel === 'dibuat')<span aria-hidden="true">{{ $arahTabel === 'asc' ? '↑' : '↓' }}</span>@endif
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->baris as $kartu)
                    @php $tenggat = $kartu->keadaanTenggat(); @endphp
                    <tr wire:key="baris-{{ $kartu->id }}" class="cursor-pointer align-top hover:bg-page" wire:click="bukaKartu({{ $kartu->id }})">
                        <td class="px-3 py-2">
                            <span class="mr-1 font-mono text-xs text-ink-muted">#{{ $kartu->id }}</span>
                            <button type="button" class="text-left font-medium text-ink hover:underline">{{ $kartu->judul }}</button>
                            @if ($kartu->order_id)
                                <span class="ml-1 rounded bg-navy/10 px-1.5 py-0.5 text-[11px] font-medium text-navy">{{ $kartu->order?->isSusulan() ? 'Susulan' : 'Order' }}</span>
                            @endif
                            @if ($kartu->templat)
                                <span class="ml-1 rounded bg-[#5E4DB2] px-1.5 py-0.5 text-[11px] font-medium text-white">Template</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-ink-muted">{{ $kartu->kolom?->nama }}</td>
                        <td class="px-3 py-2">
                            <span class="flex flex-wrap gap-1">
                                @foreach ($kartu->label as $l)
                                    <span class="inline-block h-5 max-w-[8rem] truncate rounded px-1.5 text-[11px] font-medium leading-5 {{ Warna::label($l->warna) }}">{{ $l->nama ?: Warna::namaLabel($l->warna) }}</span>
                                @endforeach
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="flex -space-x-1">
                                @foreach ($kartu->anggota as $a)
                                    <span title="{{ $a->nama ?? $a->name }}"><x-avatar :name="$a->nama ?? $a->name" size="sm" class="!h-6 !w-6 !text-[10px] ring-2 ring-white" /></span>
                                @endforeach
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            @if ($tenggat)
                                <span @class([
                                    'inline-flex items-center rounded px-1.5 py-0.5 text-xs',
                                    'bg-[#1F845A] text-white' => $tenggat === 'selesai',
                                    'bg-[#C9372C] text-white' => $tenggat === 'lewat',
                                    'bg-[#F5CD47] text-ink' => $tenggat === 'segera',
                                    'bg-[#E9EBEE] text-ink' => $tenggat === 'biasa',
                                ])>{{ $kartu->tenggat_pada->translatedFormat('j M Y') }}</span>
                            @else
                                <span class="text-ink-muted">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-ink-muted">
                            {{ $kartu->checklist_item_count ? $kartu->checklist_selesai_count.'/'.$kartu->checklist_item_count : '—' }}
                        </td>
                        <td class="px-3 py-2 text-ink-muted">{{ $kartu->created_at?->translatedFormat('j M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-10 text-center text-ink-muted">
                            {{ $this->adaSaringan ? 'Tidak ada kartu yang cocok dengan filter.' : 'Board ini belum punya kartu.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-white [&_a]:text-white [&_span]:text-white/80">{{ $this->baris->links() }}</div>
    <p class="mt-2 px-1 text-xs text-white/90">Klik baris untuk membuka kartu. Di layar kecil, tabel bisa digeser ke samping.</p>
</div>
