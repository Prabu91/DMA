@php
    use App\Livewire\Kanban\PapanBoard;
    use App\Support\Kanban\Warna;
@endphp

{{-- Tampilan tabel: semua kartu board dalam satu daftar yang bisa diurutkan. --}}
<div class="min-h-0 flex-1 overflow-auto p-3 sm:px-4">
    <div class="overflow-hidden rounded-xl bg-card">
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
                            <button type="button" class="text-left font-medium text-ink hover:underline">{{ $kartu->judul }}</button>
                            @if ($kartu->order_id)
                                <span class="ml-1 rounded bg-navy/10 px-1.5 py-0.5 text-[11px] font-medium text-navy">{{ $kartu->order?->isSusulan() ? 'Susulan' : 'Order' }}</span>
                            @endif
                            @if ($kartu->templat)
                                <span class="ml-1 rounded bg-[#5E4DB2] px-1.5 py-0.5 text-[11px] font-medium text-white">Templat</span>
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
                            {{ $this->adaSaringan ? 'Tidak ada kartu yang cocok dengan saringan.' : 'Board ini belum punya kartu.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 px-1 text-xs text-white/90">{{ $this->baris->count() }} kartu ditampilkan. Klik baris untuk membuka kartu.</p>
</div>
