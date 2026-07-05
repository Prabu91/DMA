<div>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Aturan free sekolah</h1>
            <p class="text-sm text-ink-muted">Aturan kondisional per paket (berdasarkan jumlah siswa atau omset).</p>
        </div>
        <x-button wire:click="create" size="sm">Tambah aturan</x-button>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    <div class="mb-4 sm:max-w-xs">
        <x-select wire:model.live="filterPaket" :options="$this->paketOptions" :selected="$filterPaket" placeholder="Semua paket" />
    </div>

    <x-card padding="p-0">
        @forelse ($aturan as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate text-sm text-ink">{{ $item->paket?->nama ?? '—' }}</span>
                        <x-badge variant="info">{{ \App\Models\AturanFreeSekolah::BASIS[$item->basis] ?? $item->basis }} {{ $item->operator }} {{ $item->basis === 'omset' ? 'Rp'.number_format($item->nilai, 0, ',', '.') : $item->nilai }}</x-badge>
                    </div>
                    <div class="mt-0.5 truncate text-xs text-ink-muted">
                        Free: {{ $item->hasilProduk?->nama ?? '—' }}@if ($item->hasil_ukuran) · {{ $item->hasil_ukuran }}@endif
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus aturan ini?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada aturan.</div>
        @endforelse
    </x-card>

    {{-- Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="aturan-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah aturan' : 'Tambah aturan' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-select label="Paket" wire:model="paket_id" :options="$this->paketOptions" :selected="$paket_id" placeholder="— Pilih paket —" :error="$errors->first('paket_id')" />

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select label="Basis" wire:model="basis" :options="$this->basisOptions" :selected="$basis" :error="$errors->first('basis')" />
                        <x-select label="Operator" wire:model="operator" :options="$this->operatorOptions" :selected="$operator" :error="$errors->first('operator')" />
                        <x-input label="Ambang (nilai)" type="number" min="0" wire:model="nilai" :error="$errors->first('nilai')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select label="Produk hasil (free)" wire:model="hasil_produk_id" :options="$this->produkOptions" :selected="$hasil_produk_id" placeholder="— Pilih produk —" :error="$errors->first('hasil_produk_id')" />
                        <x-input label="Ukuran hasil" wire:model="hasil_ukuran" :error="$errors->first('hasil_ukuran')" hint="Opsional, mis. 10RP." />
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
