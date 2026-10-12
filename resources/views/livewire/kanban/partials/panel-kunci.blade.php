{{--
    Panel daftar akses kunci. Dipakai di menu board, menu list, dan kartu.
    Isinya campuran: orang tertentu dan/atau peran.

    $staf  — daftar pengguna yang bisa dipilih
    $lepas — ekspresi wire:click untuk membuka kunci (kosong kalau belum terkunci)
--}}
@php
    $peranTersedia = \App\Support\Kanban\Akses::PERAN_STAF;
    $namaPeran = fn (string $p) => ucfirst(str_replace('_', ' ', $p));
@endphp

<div class="space-y-3 text-sm" x-data="{ cari: '' }">
    <p class="text-xs text-ink-muted">
        Locked items can still be opened and commented on. Only the people and roles below can move them
        or change what is inside &mdash; central admins always can.
    </p>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Roles</p>
        <div class="mt-1 flex flex-wrap gap-1">
            @foreach ($peranTersedia as $peran)
                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-page px-2 py-1 text-xs hover:bg-line">
                    <input type="checkbox" wire:model="kunciPeran" value="{{ $peran }}"
                           class="h-3.5 w-3.5 rounded border-line text-navy focus:ring-brand/40">
                    {{ $namaPeran($peran) }}
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">People</p>
        <input type="search" x-model="cari" placeholder="Search staff…" aria-label="Search staff"
               class="mt-1 block min-h-[32px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
        <ul class="mt-1 max-h-40 overflow-y-auto">
            @foreach ($staf as $u)
                @php $nm = $u->nama ?? $u->name; @endphp
                <li wire:key="kunci-staf-{{ $u->id }}" x-show="@js(mb_strtolower($nm)).includes(cari.toLowerCase())">
                    <label class="flex min-h-[32px] cursor-pointer items-center gap-2 rounded-md px-1 hover:bg-page">
                        <input type="checkbox" wire:model="kunciUser" value="{{ $u->id }}"
                               class="h-4 w-4 rounded border-line text-navy focus:ring-brand/40">
                        <span class="min-w-0 flex-1 truncate">{{ $nm }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" wire:click="simpanKunci"
                class="h-9 flex-1 rounded-md bg-navy px-3 text-sm font-medium text-white hover:bg-navy-900">Lock</button>
        @if ($lepas)
            <button type="button" wire:click="{{ $lepas }}"
                    class="h-9 rounded-md bg-[#E9EBEE] px-3 text-sm font-medium text-ink hover:bg-line">Unlock</button>
        @endif
    </div>
</div>
