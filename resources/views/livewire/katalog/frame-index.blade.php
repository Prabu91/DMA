<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Katalog'],
        ['label' => 'Frame'],
    ]" />

    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-medium text-ink">Frame</h1>
        </div>
        <x-button wire:click="create" size="sm" class="shrink-0 self-start whitespace-nowrap sm:self-auto">Tambah frame</x-button>
    </div>

    {{-- Notifikasi --}}
    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    {{-- Pencarian --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari frame…" />
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
        @forelse ($frame as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-ink">{{ $item->nama }}</span>
                        <x-badge :variant="$item->status === 'aktif' ? 'success' : 'danger'">{{ \App\Models\Frame::STATUS[$item->status] ?? $item->status }}</x-badge>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-muted">{{ $item->produk_count }} produk</div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus frame {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada frame.</div>
        @endforelse
    </x-card>

    <x-table-footer :paginator="$frame" />

    {{-- Modal form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="frame-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative w-full max-w-md rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah frame' : 'Tambah frame' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-input id="frame-nama" label="Nama frame" wire:model="nama" :error="$errors->first('nama')" placeholder="mis. Minimalis" />
                    <x-select label="Status" wire:model="status" :options="\App\Models\Frame::STATUS" :selected="$status" :error="$errors->first('status')" />

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
