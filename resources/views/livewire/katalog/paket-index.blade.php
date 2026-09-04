<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Katalog'],
        ['label' => 'Paket'],
    ]" />

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Paket</h1>
            <p class="text-sm text-ink-muted">Katalog global — kumpulan produk dalam satu paket.</p>
        </div>
        <x-button wire:click="create" size="sm">Tambah paket</x-button>
    </div>

    <x-toast :success="$success" :error="$error" />

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari paket…" />
        </div>
        <x-sort-select class="sm:w-64" :field="$sortField" :dir="$sortDir" :options="[
            'nama|asc' => 'Nama A–Z',
            'nama|desc' => 'Nama Z–A',
            'harga|asc' => 'Harga terendah',
            'harga|desc' => 'Harga tertinggi',
            'item|desc' => 'Item terbanyak',
        ]" />
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
                        Rp{{ number_format($item->harga, 0, ',', '.') }} · {{ $item->items_count }} item
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-confirm action="delete" :arg="$item->id" variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, hapus"
                        title="Hapus paket" message="Paket {{ $item->nama }} akan dihapus permanen. Lanjutkan?">Hapus</x-confirm>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada paket.</div>
        @endforelse
    </x-card>

    <x-table-footer :paginator="$paket" />

    {{-- Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="paket-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah paket' : 'Tambah paket' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-input label="Nama paket" wire:model="nama" :error="$errors->first('nama')" />
                    <x-input label="Deskripsi" wire:model="deskripsi" :error="$errors->first('deskripsi')" />
                    <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />

                    {{-- Isi paket: repeater produk (harga & free per item) --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="block text-sm font-medium text-ink">Isi paket</span>
                            <x-button type="button" wire:click="addItem" variant="ghost" size="sm">+ Tambah item</x-button>
                        </div>
                        <p class="text-xs text-ink-muted">Paket tanpa produk otomatis disimpan sebagai <span class="font-medium">Nonaktif</span>.</p>
                        @error('items')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror

                        @foreach ($items as $i => $row)
                            <div wire:key="pkitem-{{ $i }}" class="rounded-lg border border-line p-3">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <x-searchable-select label="Produk" model="items.{{ $i }}.produk_id" :options="$this->produkOptions" :selected="$row['produk_id']" placeholder="— Pilih produk —" :error="$errors->first('items.'.$i.'.produk_id')" />
                                    <x-input label="Ukuran/opsi" wire:model="items.{{ $i }}.opsi_ukuran" placeholder="mis. 10RP (opsional)" />
                                    <x-input type="number" min="1" label="Qty" wire:model="items.{{ $i }}.qty" :error="$errors->first('items.'.$i.'.qty')" />
                                    <x-input type="number" min="0" label="Harga/satuan" wire:model="items.{{ $i }}.harga" :error="$errors->first('items.'.$i.'.harga')" />
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" wire:model="items.{{ $i }}.is_free" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                        Free (bonus, harga 0)
                                    </label>
                                    <x-button type="button" wire:click="removeItem({{ $i }})" variant="ghost" size="sm">Hapus</x-button>
                                </div>
                            </div>
                        @endforeach
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
