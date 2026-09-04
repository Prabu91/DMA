<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Kecamatan'],
    ]" />

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Kecamatan</h1>
            <p class="text-sm text-ink-muted">Wilayah di bawah kota — acuan pembagian marketing (auto-assign order).</p>
        </div>
        <x-button wire:click="create" size="sm">Tambah kecamatan</x-button>
    </div>

    {{-- Notifikasi --}}
    <x-toast :success="$success" :error="$error" />

    {{-- Filter --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="min-w-[200px] flex-1">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kecamatan…" />
        </div>
        <select wire:model.live="filterKota"
                class="h-11 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
            <option value="">Semua kota</option>
            @foreach ($this->kotaOptions as $id => $nama)<option value="{{ $id }}" @selected($filterKota === (string) $id)>{{ $nama }}</option>@endforeach
        </select>
    </div>

    {{-- Daftar --}}
    <x-table min-width="640px">
        <x-slot:head>
            <x-table.th sortable field="nama" :sort="$sortField" :dir="$sortDir">Kecamatan</x-table.th>
            <x-table.th>Kota</x-table.th>
            <x-table.th sortable field="sekolah" :sort="$sortField" :dir="$sortDir" align="right">Sekolah</x-table.th>
            <x-table.th sortable field="marketing" :sort="$sortField" :dir="$sortDir" align="right">Marketing</x-table.th>
            <x-table.th align="right">Aksi</x-table.th>
        </x-slot:head>

        @forelse ($kecamatan as $item)
            <x-table.tr>
                <x-table.td class="font-medium">{{ $item->nama }}</x-table.td>
                <x-table.td><x-badge variant="neutral">{{ $item->kota?->nama ?? '—' }}</x-badge></x-table.td>
                <x-table.td align="right" muted>{{ $item->sekolah_count }}</x-table.td>
                <x-table.td align="right" muted>{{ $item->users_count }}</x-table.td>
                <x-table.td align="right" nowrap>
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-confirm action="delete" :arg="$item->id" variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, hapus"
                        title="Hapus kecamatan" message="Kecamatan {{ $item->nama }} akan dihapus permanen. Lanjutkan?">Hapus</x-confirm>
                </x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="5">Belum ada kecamatan.</x-table.empty>
        @endforelse
    </x-table>

    <div class="mt-4">{{ $kecamatan->links() }}</div>

    {{-- Modal form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="kecamatan-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative w-full max-w-md rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah kecamatan' : 'Tambah kecamatan' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-input id="kecamatan-nama" label="Nama kecamatan" wire:model="nama" :error="$errors->first('nama')" placeholder="mis. Coblong" />
                    <x-select label="Kota" wire:model="kota_id" :options="$this->kotaOptions" :selected="$kota_id" placeholder="— Pilih kota —" :error="$errors->first('kota_id')" />

                    <div class="flex items-center gap-3 pt-2">
                        <x-button type="submit">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Simpan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </x-button>
                        <x-button type="button" wire:click="$set('showForm', false)" variant="ghost">Batal</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
