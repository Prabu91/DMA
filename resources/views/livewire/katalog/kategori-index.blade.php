<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Katalog'],
        ['label' => 'Kategori'],
    ]" />

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Kategori</h1>
            <p class="text-sm text-ink-muted">Katalog global — berlaku untuk semua cabang.</p>
        </div>
        <x-button wire:click="create" size="sm">Tambah kategori</x-button>
    </div>

    {{-- Notifikasi --}}
    <x-toast :success="$success" :error="$error" />

    {{-- Pencarian --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kategori…" />
        </div>
        <x-sort-select class="sm:w-64" :field="$sortField" :dir="$sortDir" :options="[
            'nama|asc' => 'Nama A–Z',
            'nama|desc' => 'Nama Z–A',
            'produk|desc' => 'Produk terbanyak',
            'produk|asc' => 'Produk tersedikit',
        ]" />
    </div>

    {{-- Daftar --}}
    <x-card padding="p-0">
        @forelse ($kategori as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="truncate text-sm text-ink">{{ $item->nama }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        @if ($item->pakai_desain)
                            <x-badge variant="brand">Pakai desain</x-badge>
                        @else
                            <x-badge variant="neutral">Tanpa desain</x-badge>
                        @endif
                        <x-badge variant="navy">{{ \App\Models\Kategori::grupLabel($item->grup) }}</x-badge>
                        <span class="text-xs text-ink-muted">{{ $item->produk_count }} produk · {{ $item->desain_count }} desain</span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-confirm action="delete" :arg="$item->id" variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, hapus"
                        title="Hapus kategori" message="Kategori {{ $item->nama }} akan dihapus permanen. Lanjutkan?">Hapus</x-confirm>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada kategori.</div>
        @endforelse
    </x-card>

    <x-table-footer :paginator="$kategori" />

    {{-- Modal form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="kategori-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative w-full max-w-md rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah kategori' : 'Tambah kategori' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-input id="kategori-nama" label="Nama kategori" wire:model="nama" :error="$errors->first('nama')" placeholder="mis. Wisuda" />

                    <x-select label="Grup laporan (Finance)" wire:model="grup" :options="\App\Models\Kategori::GRUP" :selected="$grup" :error="$errors->first('grup')" hint="Menentukan bucket di laporan sales: Reguler/OB/YB/Souvenir." />

                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="pakai_desain" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                        <span class="text-sm text-ink">Pakai desain</span>
                    </label>
                    <p class="text-xs text-ink-muted">Aktifkan untuk kategori seperti wisuda, manasik, pas foto, kalender, bersama, angkatan.</p>

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
