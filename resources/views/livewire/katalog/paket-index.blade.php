<div>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Paket</h1>
            <p class="text-sm text-ink-muted">Katalog global — kumpulan produk dalam satu paket.</p>
        </div>
        <x-button wire:click="create" size="sm">Tambah paket</x-button>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    <div class="mb-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari paket…" />
    </div>

    <x-card padding="p-0">
        @forelse ($paket as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm text-ink">{{ $item->nama }}</span>
                        <x-badge :variant="$item->status === 'aktif' ? 'success' : 'danger'">{{ \App\Models\Paket::STATUS[$item->status] ?? $item->status }}</x-badge>
                    </div>
                    <div class="mt-0.5 truncate text-xs text-ink-muted">
                        Rp{{ number_format($item->harga, 0, ',', '.') }} · {{ $item->produk_count }} produk
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus paket {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada paket.</div>
        @endforelse
    </x-card>

    {{-- Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="paket-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah paket' : 'Tambah paket' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-input label="Nama paket" wire:model="nama" :error="$errors->first('nama')" />
                    <x-input label="Deskripsi" wire:model="deskripsi" :error="$errors->first('deskripsi')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Harga (Rp)" type="number" min="0" wire:model="harga" :error="$errors->first('harga')" />
                        <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />
                    </div>

                    <div class="space-y-1.5">
                        <span class="block text-sm font-medium text-ink">Produk termasuk</span>
                        <div class="max-h-56 overflow-y-auto rounded-lg border border-line">
                            @forelse ($this->produkList as $p)
                                <label class="flex items-center justify-between gap-3 border-b border-line px-3 py-2 last:border-b-0 hover:bg-page">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="selectedProduk" value="{{ $p->id }}"
                                               class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                        <span class="text-sm text-ink">{{ $p->nama }}</span>
                                    </span>
                                    <span class="text-xs text-ink-muted">Rp{{ number_format($p->harga, 0, ',', '.') }}</span>
                                </label>
                            @empty
                                <p class="px-3 py-3 text-sm text-ink-muted">Belum ada produk untuk dipilih.</p>
                            @endforelse
                        </div>
                        @error('selectedProduk.*')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                    </div>

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
