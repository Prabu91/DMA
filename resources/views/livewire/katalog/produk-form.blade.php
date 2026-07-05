<div>
    <div class="mb-6">
        <a href="{{ route('produk.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            Kembali ke produk
        </a>
        <h1 class="text-lg font-medium text-ink">{{ $produkId ? 'Ubah produk' : 'Tambah produk' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Informasi produk --}}
        <x-card title="Informasi produk">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select label="Kategori" wire:model="kategori_id" :options="$this->kategoriOptions" :selected="$kategori_id" placeholder="— Pilih kategori —" :error="$errors->first('kategori_id')" />
                <x-input label="Nama produk" wire:model="nama" :error="$errors->first('nama')" />
                <x-select label="Gaya" wire:model="gaya" :options="$this->gayaOptions" :selected="$gaya" placeholder="— Tanpa gaya —" :error="$errors->first('gaya')" hint="Atribut produk, tidak terkait desain." />
                <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />
                <x-input label="Harga (Rp)" type="number" min="0" wire:model="harga" :error="$errors->first('harga')" />
                <x-input label="Deskripsi" wire:model="deskripsi" :error="$errors->first('deskripsi')" class="sm:col-span-2" />

                {{-- Foto --}}
                <div class="space-y-1.5 sm:col-span-2">
                    <span class="block text-sm font-medium text-ink">Foto produk</span>
                    <div class="flex items-center gap-4">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-page">
                            @if ($foto)
                                <img src="{{ $foto->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                            @elseif ($fotoExisting)
                                <img src="{{ asset('storage/'.$fotoExisting) }}" alt="" class="h-full w-full object-cover">
                            @else
                                <svg class="h-6 w-6 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                            @endif
                        </div>
                        <div>
                            <input type="file" wire:model="foto" accept="image/*"
                                   class="block text-sm text-ink-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-card file:px-3 file:py-2 file:text-sm file:text-ink hover:file:bg-page">
                            <div wire:loading wire:target="foto" class="mt-1 text-xs text-ink-muted">Mengunggah…</div>
                            <p class="mt-1 text-xs text-ink-muted">JPG/PNG, maks 2 MB.</p>
                            @error('foto')<p class="mt-1 text-xs text-status-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Opsi (produk_opsi) --}}
        <x-card>
            <x-slot name="actions">
                <x-button type="button" wire:click="addOpsi" variant="secondary" size="sm">Tambah opsi</x-button>
            </x-slot>
            <x-slot name="title">Opsi produk (mis. ukuran)</x-slot>

            @forelse ($opsi as $i => $row)
                <div wire:key="opsi-{{ $i }}" class="mb-3 rounded-lg border border-line p-3 last:mb-0">
                    <div class="grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-3">
                            <x-input label="Tipe" wire:model="opsi.{{ $i }}.tipe_opsi" :error="$errors->first('opsi.'.$i.'.tipe_opsi')" placeholder="ukuran" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input label="Nilai" wire:model="opsi.{{ $i }}.nilai_opsi" :error="$errors->first('opsi.'.$i.'.nilai_opsi')" placeholder="mis. 10RP" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input label="Harga override" type="number" min="0" wire:model="opsi.{{ $i }}.harga_override" :error="$errors->first('opsi.'.$i.'.harga_override')" hint="Kosong = pakai harga produk." />
                        </div>
                        <div class="flex items-end justify-between gap-2 sm:col-span-3">
                            <label class="flex items-center gap-2 pb-2.5">
                                <input type="checkbox" wire:model="opsi.{{ $i }}.is_wajib" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                <span class="text-sm text-ink">Wajib</span>
                            </label>
                            <x-button type="button" wire:click="removeOpsi({{ $i }})" variant="ghost" size="sm">Hapus</x-button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-muted">Belum ada opsi. Tambahkan bila produk punya varian ukuran.</p>
            @endforelse
        </x-card>

        {{-- Bonus tetap (produk_bonus) --}}
        <x-card>
            <x-slot name="actions">
                <x-button type="button" wire:click="addBonus" variant="secondary" size="sm">Tambah bonus</x-button>
            </x-slot>
            <x-slot name="title">Bonus tetap</x-slot>
            <x-slot name="subtitle">Bonus otomatis untuk produk ini (mekanisme free sekolah B — satuan).</x-slot>

            @forelse ($bonus as $i => $row)
                <div wire:key="bonus-{{ $i }}" class="mb-3 rounded-lg border border-line p-3 last:mb-0">
                    <div class="grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-7">
                            <x-select label="Produk bonus" wire:model="bonus.{{ $i }}.bonus_produk_id" :options="$this->produkBonusOptions" :selected="$row['bonus_produk_id'] ?? null" placeholder="— Pilih produk —" :error="$errors->first('bonus.'.$i.'.bonus_produk_id')" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input label="Qty" type="number" min="1" wire:model="bonus.{{ $i }}.qty" :error="$errors->first('bonus.'.$i.'.qty')" />
                        </div>
                        <div class="flex items-end sm:col-span-2">
                            <x-button type="button" wire:click="removeBonus({{ $i }})" variant="ghost" size="sm">Hapus</x-button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-muted">Belum ada bonus.</p>
            @endforelse
        </x-card>

        <div class="flex items-center gap-3">
            <x-button type="submit">
                <span wire:loading.remove wire:target="save">{{ $produkId ? 'Simpan perubahan' : 'Simpan produk' }}</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </x-button>
            <x-button :href="route('produk.index')" variant="ghost" wire:navigate>Batal</x-button>
        </div>
    </form>
</div>
