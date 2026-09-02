<div>
    <div class="mb-6">
        <a href="{{ route('app.produk.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
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
                <x-select label="Frame" wire:model="frame" :options="$this->frameOptions" :selected="$frame" placeholder="— Tanpa frame —" :error="$errors->first('frame')" hint="Atribut produk (dikelola di menu Frame)." />
                <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />
                <x-input label="Harga (Rp)" type="number" min="0" wire:model="harga" :error="$errors->first('harga')" hint="Harga per satu produk (dikali jumlah yang dipesan)." />
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

        {{-- Varian produk (produk_opsi dikelompokkan per tipe) --}}
        <x-card>
            <x-slot name="actions">
                <x-button type="button" wire:click="addVariant" variant="secondary" size="sm">Tambah varian</x-button>
            </x-slot>
            <x-slot name="title">Varian produk</x-slot>
            <x-slot name="subtitle">Mis. varian <span class="font-medium">Ukuran</span> dgn nilai 8R &amp; 10RP, atau varian <span class="font-medium">Box</span> dgn pilihannya. Satu tipe = satu varian, tambah nilainya di dalam.</x-slot>

            <datalist id="varian-tipe"><option value="ukuran"></option><option value="box"></option><option value="warna"></option><option value="bahan"></option></datalist>

            @forelse ($variants as $vi => $v)
                <div wire:key="var-{{ $vi }}" class="mb-3 rounded-xl border border-line p-3 last:mb-0">
                    <div class="flex flex-wrap items-end gap-3 border-b border-line pb-3">
                        <div class="w-44">
                            <x-input label="Tipe varian" wire:model="variants.{{ $vi }}.tipe" list="varian-tipe" placeholder="ukuran / box / warna" :error="$errors->first('variants.'.$vi.'.tipe')" />
                        </div>
                        <label class="flex items-center gap-2 pb-2.5">
                            <input type="checkbox" wire:model="variants.{{ $vi }}.is_wajib" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                            <span class="text-sm text-ink">Wajib dipilih</span>
                        </label>
                        <x-button type="button" wire:click="removeVariant({{ $vi }})" variant="ghost" size="sm" class="ml-auto text-status-danger">Hapus varian</x-button>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach (($v['values'] ?? []) as $ki => $val)
                            <div wire:key="var-{{ $vi }}-val-{{ $ki }}" class="flex flex-wrap items-end gap-2">
                                <div class="w-40">
                                    <x-input label="Nilai" wire:model="variants.{{ $vi }}.values.{{ $ki }}.nilai" placeholder="mis. 8R" :error="$errors->first('variants.'.$vi.'.values.'.$ki.'.nilai')" />
                                </div>
                                <div class="w-44">
                                    <x-input label="Harga override" type="number" min="0" wire:model="variants.{{ $vi }}.values.{{ $ki }}.harga_override" hint="Kosong = harga produk." />
                                </div>
                                <button type="button" wire:click="removeValue({{ $vi }}, {{ $ki }})" class="mb-2.5 grid h-8 w-8 place-items-center rounded-lg border border-line text-status-danger hover:bg-status-danger/10" title="Hapus nilai">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        @endforeach
                        <x-button type="button" wire:click="addValue({{ $vi }})" variant="ghost" size="sm">+ Tambah nilai</x-button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-muted">Belum ada varian. Tambahkan bila produk punya pilihan (mis. ukuran, box).</p>
            @endforelse
        </x-card>

        {{-- ============ DESAIN PRODUK (pivot desain↔produk) ============ --}}
        @if ($this->pakaiDesain)
            <x-card>
                <x-slot name="title">Desain produk</x-slot>
                <x-slot name="subtitle">Upload / tempel desain untuk produk ini. Centang ukuran bila desain khusus ukuran tertentu; kosong = semua ukuran.</x-slot>

                @if (! $produkId)
                    <div class="rounded-lg border border-status-pending/30 bg-status-pending/10 p-3 text-sm text-ink">
                        Simpan produk dulu, lalu kamu bisa menambah &amp; menempel desain di sini.
                    </div>
                @else
                    @if ($desainMsg)<p class="mb-3 text-sm font-medium text-status-success">{{ $desainMsg }}</p>@endif

                    {{-- Daftar desain terpasang --}}
                    @if ($this->attachedDesigns->isEmpty())
                        <p class="text-sm text-ink-muted">Belum ada desain untuk produk ini.</p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($this->attachedDesigns as $d)
                                <div class="flex gap-3 rounded-lg border border-line p-3">
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-page">
                                        @if ($d->foto_preview)
                                            <img src="{{ asset('storage/'.$d->foto_preview) }}" loading="lazy" class="max-h-full max-w-full object-contain" alt="">
                                        @else
                                            <svg class="h-6 w-6 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-sm font-medium text-ink">{{ $d->kode }}</span>
                                            <button type="button" wire:click="lepasDesain({{ $d->id }})" class="shrink-0 text-xs font-medium text-status-danger hover:underline">Lepas</button>
                                        </div>
                                        @if (! empty($this->ukuranOpsiForm))
                                            <div x-data="{ uk: {{ \Illuminate\Support\Js::from($d->pivot->ukuran ?? []) }} }" class="mt-1.5 flex flex-wrap gap-2">
                                                @foreach ($this->ukuranOpsiForm as $u)
                                                    <label class="inline-flex items-center gap-1 text-xs text-ink">
                                                        <input type="checkbox" value="{{ $u }}" x-model="uk"
                                                               x-on:change="$wire.setUkuranDesain({{ $d->id }}, uk)"
                                                               class="h-3.5 w-3.5 rounded border-line text-brand focus:ring-1 focus:ring-brand/30">
                                                        {{ $u }}
                                                    </label>
                                                @endforeach
                                                <span class="text-xs text-ink-muted" x-show="uk.length === 0">semua ukuran</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Tempel desain yang sudah ada --}}
                    @if (! empty($this->availableDesigns))
                        <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-line pt-4">
                            <div class="min-w-[200px] flex-1">
                                <x-select label="Tempel desain yang sudah ada" wire:model="tempelDesainId" :options="$this->availableDesigns" :selected="$tempelDesainId" placeholder="— Pilih desain —" />
                            </div>
                            <x-button type="button" wire:click="tempelDesain" variant="secondary" size="sm">Tempel</x-button>
                        </div>
                    @endif

                    {{-- Tambah desain baru (upload) --}}
                    <div class="mt-4 border-t border-line pt-4">
                        <p class="mb-2 text-sm font-medium text-ink">Tambah desain baru</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input label="Kode desain" wire:model="desainKode" :error="$errors->first('desainKode')" placeholder="mis. ERP-001" />
                            <x-select label="Orientasi" wire:model="desainOrientasi" :options="\App\Models\Desain::ORIENTASI" :selected="$desainOrientasi" placeholder="— Tidak ditentukan —" />
                            <x-input label="Tahun ajaran" wire:model="desainTahun" :error="$errors->first('desainTahun')" placeholder="mis. 2026/2027" />
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Foto desain</span>
                                <input type="file" wire:model="desainFoto" accept="image/*" class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
                                <div wire:loading wire:target="desainFoto" class="text-xs text-ink-muted">Mengunggah…</div>
                                @error('desainFoto')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        @if (! empty($this->ukuranOpsiForm))
                            <div class="mt-3">
                                <span class="block text-xs font-medium text-ink-muted">Berlaku untuk ukuran (kosong = semua)</span>
                                <div class="mt-1.5 flex flex-wrap gap-3">
                                    @foreach ($this->ukuranOpsiForm as $u)
                                        <label class="inline-flex items-center gap-1.5 text-sm text-ink">
                                            <input type="checkbox" value="{{ $u }}" wire:model="desainUkuran" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                            {{ $u }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <x-button type="button" wire:click="tambahDesainBaru" variant="secondary" size="sm" class="mt-3">
                            <span wire:loading.remove wire:target="tambahDesainBaru,desainFoto">Tambah desain</span>
                            <span wire:loading wire:target="tambahDesainBaru,desainFoto">Menyimpan…</span>
                        </x-button>
                    </div>
                @endif
            </x-card>
        @endif

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
            <x-button :href="route('app.produk.index')" variant="ghost" wire:navigate>Batal</x-button>
        </div>
    </form>
</div>
