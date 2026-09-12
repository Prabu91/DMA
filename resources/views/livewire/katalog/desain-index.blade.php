<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Katalog'],
        ['label' => 'Desain'],
    ]" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-medium text-ink">Desain</h1>
            <p class="text-sm text-ink-muted">Kode katalog desain — menempel ke kategori.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2 self-start sm:self-auto">
            <x-button wire:click="bukaBulk" variant="secondary" size="sm" class="whitespace-nowrap">Unggah massal</x-button>
            <x-button wire:click="create" size="sm" class="whitespace-nowrap">Tambah desain</x-button>
        </div>
    </div>

    <x-toast :success="$success" :error="$error" />

    {{-- Filter --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-select wire:model.live="filterKategori" :options="$this->kategoriFilterOptions" :selected="$filterKategori" placeholder="Semua kategori" />
        <x-select wire:model.live="filterTahun" :options="$this->tahunOptions" :selected="$filterTahun" placeholder="Semua tahun ajaran" />
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari kode…" />
    </div>

    @if ($terpilih)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-brand/30 bg-brand/5 px-4 py-2.5">
            <span class="text-sm text-ink">{{ count($terpilih) }} desain dipilih.</span>
            <div class="flex items-center gap-2">
                <x-button wire:click="$set('terpilih', [])" variant="ghost" size="sm">Batal pilih</x-button>
                <x-button wire:click="mintaHapusMassal" variant="danger" size="sm">Hapus terpilih</x-button>
            </div>
        </div>
    @endif

    <x-card padding="p-0">
        @forelse ($desain as $item)
            <div class="flex flex-col gap-2 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <input type="checkbox" wire:model.live="terpilih" value="{{ $item->id }}"
                           aria-label="Pilih desain {{ $item->kode }}"
                           class="mt-3.5 h-4 w-4 shrink-0 rounded border-line text-brand focus:ring-brand/40">
                    @if ($item->foto_preview)
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-page">
                        <img src="{{ asset('storage/'.$item->foto_preview) }}" alt="" loading="lazy" class="max-h-full max-w-full object-contain">
                    </div>
                    @else
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-line bg-page text-ink-muted">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-sm font-medium text-ink">{{ $item->kode }}</span>
                            @if ($item->orientasi)<x-badge variant="neutral">{{ \App\Models\Desain::ORIENTASI[$item->orientasi] ?? $item->orientasi }}</x-badge>@endif
                            <x-badge :variant="$item->status === 'aktif' ? 'success' : 'danger'">{{ \App\Models\Desain::STATUS[$item->status] ?? $item->status }}</x-badge>
                        </div>
                        <div class="mt-0.5 text-xs text-ink-muted">
                            {{ $item->kategori?->nama ?? '—' }}
                            · @if ($item->products_count > 0)<span class="font-medium text-ink">{{ $item->products->pluck('nama')->join(', ') }}</span>@else<span class="italic">belum dipakai produk</span>@endif
                            @if ($item->seri) · Seri {{ $item->seri }}@endif
                            · {{ $item->tahun_ajaran ?: '—' }}
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 pl-[3.75rem] sm:pl-0">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    @if ($item->order_items_count > 0 && $this->isSuperAdmin)
                        <x-confirm action="forceDelete" :arg="$item->id" title="Hapus paksa desain"
                                   message="Desain {{ $item->kode }} dipakai di {{ $item->order_items_count }} item order. Hapus paksa akan melepas referensi di order tersebut lalu menghapus desain. Lanjutkan?"
                                   confirm-label="Ya, hapus paksa" variant="ghost" confirm-variant="danger" size="sm">Hapus paksa</x-confirm>
                    @else
                        <x-confirm action="delete" :arg="$item->id" title="Hapus desain" message="Hapus desain {{ $item->kode }}?" confirm-label="Ya, hapus" variant="ghost" confirm-variant="danger" size="sm">Hapus</x-confirm>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada desain.</div>
        @endforelse
    </x-card>

    <x-table-footer :paginator="$desain" />

    {{-- Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="desain-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah desain' : 'Tambah desain' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select label="Kategori" wire:model="kategori_id" :options="$this->kategoriDesainOptions" :selected="$kategori_id" placeholder="— Pilih kategori —" :error="$errors->first('kategori_id')" hint="Untuk pengelompokan." />
                        <x-input label="Kode" wire:model="kode" :error="$errors->first('kode')" placeholder="mis. ERP-001" />
                        <x-input label="Seri" wire:model="seri" :error="$errors->first('seri')" hint="Opsional." />
                        <x-select label="Orientasi" wire:model="orientasi" :options="$this->orientasiOptions" :selected="$orientasi" placeholder="— Tidak ditentukan —" :error="$errors->first('orientasi')" />
                        <x-input label="Tahun ajaran" wire:model="tahun_ajaran" :error="$errors->first('tahun_ajaran')" placeholder="mis. 2025/2026" />
                        <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />
                    </div>

                    <div class="rounded-lg border border-status-info/20 bg-status-info/10 p-3 text-xs text-ink">
                        Penempelan desain ke <span class="font-medium">produk</span> &amp; pemilihan <span class="font-medium">ukuran</span> dilakukan di <span class="font-medium">halaman Produk</span> (blok Desain), bukan di sini.
                    </div>

                    {{-- Foto preview --}}
                    <div class="space-y-1.5">
                        <span class="block text-sm font-medium text-ink">Foto preview</span>
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-page">
                                @if ($foto_preview)
                                    <img src="{{ $foto_preview->temporaryUrl() }}" alt="" class="max-h-full max-w-full object-contain">
                                @elseif ($fotoExisting)
                                    <img src="{{ asset('storage/'.$fotoExisting) }}" alt="" class="max-h-full max-w-full object-contain">
                                @else
                                    <svg class="h-6 w-6 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <input type="file" wire:model="foto_preview" accept="image/*"
                                       class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-card file:px-3 file:py-2 file:text-sm file:text-ink hover:file:bg-page">
                                <div wire:loading wire:target="foto_preview" class="mt-1 text-xs text-ink-muted">Mengunggah…</div>
                                <p class="mt-1 text-xs text-ink-muted">JPG/PNG, maks 4 MB.</p>
                                @error('foto_preview')<p class="mt-1 text-xs text-status-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
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
    {{-- Modal unggah massal --}}
    @if ($showBulk)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="desain-bulk-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="tutupBulk"></div>
            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">Unggah desain massal</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Pilih banyak JPG/PNG sekaligus. <span class="font-medium text-ink">Kode desain diambil dari nama berkas</span>
                    (mis. <span class="font-mono">WSD-012.jpg</span> → <span class="font-mono">WSD-012</span>), dan orientasi dibaca dari dimensi gambarnya.
                </p>

                <form wire:submit="simpanBulk" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select label="Kategori" wire:model="bulkKategoriId" :options="$this->kategoriDesainOptions" :selected="$bulkKategoriId" placeholder="— Pilih kategori —" :error="$errors->first('bulkKategoriId')" hint="Berlaku untuk seluruh berkas." />
                        <x-input label="Tahun ajaran" wire:model="bulkTahun" :error="$errors->first('bulkTahun')" placeholder="mis. 2025/2026" />
                    </div>

                    <div class="space-y-1.5">
                        <span class="block text-sm font-medium text-ink">Berkas desain</span>
                        <input type="file" wire:model="bulkFiles" accept="image/*" multiple
                               class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-card file:px-3 file:py-2 file:text-sm file:text-ink hover:file:bg-page">
                        <div wire:loading wire:target="bulkFiles" class="text-xs text-ink-muted">Mengunggah…</div>
                        <p class="text-xs text-ink-muted">JPG/PNG, maks 4 MB per berkas, maksimal {{ \App\Services\DesainBulkUpload::MAKS_BERKAS }} berkas.</p>
                        @error('bulkFiles')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                        @foreach ($errors->get('bulkFiles.*') as $pesan)
                            <p class="text-xs text-status-danger">{{ $pesan[0] }}</p>
                        @endforeach
                    </div>

                    @if ($bulkFiles)
                        <div class="rounded-lg border border-line bg-page/50 p-3">
                            <p class="text-xs font-medium text-ink">{{ count($bulkFiles) }} berkas siap — kode yang akan dibuat:</p>
                            <ul class="mt-1.5 max-h-32 space-y-0.5 overflow-y-auto text-xs text-ink-muted">
                                @foreach ($bulkFiles as $f)
                                    <li class="font-mono">{{ app(\App\Services\DesainBulkUpload::class)->kodeDariNamaBerkas($f->getClientOriginalName()) }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs text-ink-muted">Kode yang sudah ada di katalog akan dilewati, bukan ditimpa.</p>
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-2">
                        <x-button type="submit">
                            <span wire:loading.remove wire:target="simpanBulk">Unggah semua</span>
                            <span wire:loading wire:target="simpanBulk">Menyimpan…</span>
                        </x-button>
                        <x-button type="button" wire:click="tutupBulk" variant="ghost">Batal</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal konfirmasi hapus massal --}}
    @if ($konfirmasiHapusMassal)
        @php $rencana = $this->rencanaHapusMassal; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="desain-hapus-massal">
            <div class="absolute inset-0 bg-ink/40" wire:click="batalHapusMassal"></div>
            <div class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">Hapus desain terpilih</h2>

                @if ($rencana['boleh']->isEmpty())
                    <p class="mt-2 text-sm text-ink-muted">Tidak ada desain yang bisa dihapus dari pilihan ini.</p>
                @else
                    <p class="mt-2 text-sm text-ink-muted">
                        <span class="font-medium text-ink">{{ $rencana['boleh']->count() }} desain</span> akan dihapus permanen beserta berkas fotonya.
                    </p>
                    <ul class="mt-2 max-h-32 space-y-0.5 overflow-y-auto text-xs text-ink-muted">
                        @foreach ($rencana['boleh'] as $d)
                            <li class="font-mono">{{ $d->kode }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($rencana['tertahan']->isNotEmpty())
                    <div class="mt-3 rounded-lg border border-status-danger/20 bg-status-danger/10 p-3 text-xs text-ink">
                        <p class="font-medium">{{ $rencana['tertahan']->count() }} dilewati — masih dipakai:</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($rencana['tertahan'] as $d)
                                @php
                                    $sebab = [];
                                    if ($d->order_items_count > 0) { $sebab[] = $d->order_items_count.' item order'; }
                                    if ($d->products_count > 0) { $sebab[] = $d->products_count.' produk'; }
                                @endphp
                                <li><span class="font-mono">{{ $d->kode }}</span> — {{ implode(', ', $sebab) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-5 flex items-center justify-end gap-3">
                    <x-button type="button" wire:click="batalHapusMassal" variant="ghost">Batal</x-button>
                    <x-button type="button" wire:click="hapusMassal" variant="danger" :disabled="$rencana['boleh']->isEmpty()">
                        <span wire:loading.remove wire:target="hapusMassal">Ya, hapus</span>
                        <span wire:loading wire:target="hapusMassal">Menghapus…</span>
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
