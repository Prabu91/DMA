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
                <x-select label="Kategori" wire:model.live="kategori_id" :options="$this->kategoriOptions" :selected="$kategori_id" placeholder="— Pilih kategori —" :error="$errors->first('kategori_id')" />
                <x-input label="Nama produk" wire:model="nama" :error="$errors->first('nama')" />
                <x-select label="Frame" wire:model="frame" :options="$this->frameOptions" :selected="$frame" placeholder="— Tanpa frame —" :error="$errors->first('frame')" hint="Atribut produk (dikelola di menu Frame)." />
                <x-select label="Status" wire:model="status" :options="$this->statusOptions" :selected="$status" :error="$errors->first('status')" />
                <x-input label="Harga (Rp)" type="number" min="0" wire:model="harga" :error="$errors->first('harga')" hint="Harga per satu produk (dikali jumlah yang dipesan)." />
                <x-input label="Deskripsi" wire:model="deskripsi" :error="$errors->first('deskripsi')" class="sm:col-span-2" />

                {{-- Foto --}}
                <div class="space-y-1.5 sm:col-span-2">
                    <span class="block text-sm font-medium text-ink">Foto produk</span>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-page">
                            @if ($foto)
                                <img src="{{ $foto->temporaryUrl() }}" alt="" class="max-h-full max-w-full object-contain">
                            @elseif ($fotoExisting)
                                <img src="{{ asset('storage/'.$fotoExisting) }}" alt="" class="max-h-full max-w-full object-contain">
                            @else
                                <svg class="h-6 w-6 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <input type="file" wire:model="foto" accept="image/*"
                                   class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-card file:px-3 file:py-2 file:text-sm file:text-ink hover:file:bg-page">
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
                            <x-input label="Tipe varian" wire:model.live.debounce.500ms="variants.{{ $vi }}.tipe" list="varian-tipe" placeholder="ukuran / box / warna" :error="$errors->first('variants.'.$vi.'.tipe')" />
                        </div>
                        <label class="flex min-h-[44px] items-center gap-2">
                            <input type="checkbox" wire:model="variants.{{ $vi }}.is_wajib" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                            <span class="text-sm text-ink">Wajib dipilih</span>
                        </label>
                        <x-button type="button" wire:click="removeVariant({{ $vi }})" variant="ghost" size="sm" class="ml-auto text-status-danger">Hapus varian</x-button>
                    </div>

                    {{-- Nilai varian. Label hanya di baris pertama supaya kolom tetap sejajar. --}}
                    <div class="mt-3 space-y-2">
                        @foreach (($v['values'] ?? []) as $ki => $val)
                            <div wire:key="var-{{ $vi }}-val-{{ $ki }}" class="flex flex-wrap items-end gap-2">
                                <div class="w-40">
                                    <x-input :label="$ki === 0 ? 'Nilai' : null" wire:model.live.debounce.500ms="variants.{{ $vi }}.values.{{ $ki }}.nilai" placeholder="mis. 8R" :error="$errors->first('variants.'.$vi.'.values.'.$ki.'.nilai')" />
                                </div>
                                <div class="w-44">
                                    <x-input :label="$ki === 0 ? 'Harga override' : null" type="number" min="0" wire:model="variants.{{ $vi }}.values.{{ $ki }}.harga_override" placeholder="Ikut harga produk" />
                                </div>
                                <button type="button" wire:click="removeValue({{ $vi }}, {{ $ki }})" class="grid h-11 w-11 shrink-0 place-items-center rounded-lg border border-line text-ink-muted transition hover:border-status-danger/50 hover:text-status-danger" title="Hapus nilai">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <x-button type="button" wire:click="addValue({{ $vi }})" variant="ghost" size="sm">+ Tambah nilai</x-button>
                            <p class="text-xs text-ink-muted">Harga override kosong = memakai harga produk.</p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-muted">Belum ada varian. Tambahkan bila produk punya pilihan (mis. ukuran, box).</p>
            @endforelse
        </x-card>

        {{-- ============ DESAIN PRODUK (aset pakai-ulang lintas kategori) ============ --}}
        @if ($this->pakaiDesain)
            <x-card>
                <x-slot name="title">Desain produk</x-slot>
                <x-slot name="subtitle">Desain itu aset pakai-ulang — satu desain boleh dipakai banyak produk walau beda kategori. Ikut tersimpan saat produk disimpan.</x-slot>

                @if ($desainMsg)
                    <p class="mb-3 rounded-lg bg-status-success/10 px-3 py-2 text-sm font-medium text-status-success">{{ $desainMsg }}</p>
                @endif

                {{-- Desain yang dipakai produk ini --}}
                @if (empty($desains))
                    <p class="rounded-xl border border-dashed border-line px-3 py-5 text-center text-sm text-ink-muted">
                        Belum ada desain. Cari desain yang sudah ada, atau buat baru di bawah.
                    </p>
                @else
                    <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line">
                        @foreach ($desains as $i => $d)
                            <li wire:key="dsn-{{ $i }}-{{ $d['kode'] }}" class="flex items-start gap-3 p-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-page">
                                    @if (! empty($d['foto']))
                                        <img src="{{ asset('storage/'.$d['foto']) }}" loading="lazy" alt="" class="max-h-full max-w-full object-contain">
                                    @else
                                        <svg class="h-5 w-5 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="truncate text-sm font-medium text-ink">{{ $d['kode'] }}</span>
                                        @if (empty($d['id']))
                                            <span class="rounded-full bg-brand/10 px-1.5 py-0.5 text-[11px] font-medium text-brand">baru</span>
                                        @endif
                                        @if (! empty($d['kategori']))
                                            <span class="text-xs text-ink-muted">{{ $d['kategori'] }}</span>
                                        @endif
                                    </div>

                                    @if (! empty($this->ukuranOpsiForm))
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @foreach ($this->ukuranOpsiForm as $u)
                                                @php $aktif = in_array($u, $d['ukuran'] ?? [], true); @endphp
                                                <button type="button" wire:click="toggleUkuranDesain({{ $i }}, @js($u))"
                                                        class="min-h-[28px] rounded-full border px-2.5 text-xs transition {{ $aktif ? 'border-brand bg-brand text-white' : 'border-line text-ink-muted hover:border-brand/60 hover:text-ink' }}">
                                                    {{ $u }}
                                                </button>
                                            @endforeach
                                            @if (empty($d['ukuran']))
                                                <span class="text-xs text-ink-muted">semua ukuran</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <button type="button" wire:click="hapusDesain({{ $i }})" title="Keluarkan dari produk ini"
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-line text-ink-muted hover:border-status-danger/50 hover:text-status-danger">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Cari desain lama / buat baru --}}
                <div class="mt-3" x-data="{ pool: false, baru: false }" x-on:click.outside="pool = false">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="min-w-[220px] flex-1">
                            <input type="search" wire:model.live.debounce.300ms="desainCari" x-on:focus="pool = true"
                                   placeholder="Cari desain yang sudah ada (semua kategori)…"
                                   class="block min-h-[44px] w-full rounded-lg border border-line bg-card px-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                        </div>
                        <x-button type="button" variant="secondary" size="sm" x-on:click="baru = ! baru; pool = false">
                            <span x-text="baru ? 'Tutup' : '+ Desain baru'">+ Desain baru</span>
                        </x-button>
                    </div>

                    {{-- Pool desain (lintas kategori) --}}
                    <div x-show="pool" x-cloak x-transition.opacity class="mt-2">
                        @if ($this->hasilCariDesain->isEmpty())
                            <p class="rounded-lg border border-line px-3 py-2 text-sm text-ink-muted">
                                {{ $desainCari !== '' ? 'Tidak ada desain yang cocok.' : 'Belum ada desain tersimpan.' }}
                            </p>
                        @else
                            <ul class="divide-y divide-line overflow-hidden rounded-lg border border-line">
                                @foreach ($this->hasilCariDesain as $opsi)
                                    <li wire:key="pool-{{ $opsi->id }}">
                                        <button type="button" wire:click="pilihDesain({{ $opsi->id }})"
                                                class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-page">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-md bg-page">
                                                @if ($opsi->foto_preview)
                                                    <img src="{{ asset('storage/'.$opsi->foto_preview) }}" loading="lazy" alt="" class="max-h-full max-w-full object-contain">
                                                @else
                                                    <svg class="h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('photo') }}" /></svg>
                                                @endif
                                            </div>
                                            <span class="truncate text-sm text-ink">{{ $opsi->kode }}</span>
                                            <span class="ml-auto shrink-0 text-xs text-ink-muted">{{ $opsi->kategori?->nama }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Buat desain baru --}}
                    <div x-show="baru" x-cloak x-transition class="mt-3 rounded-xl border border-line bg-page/50 p-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input label="Kode desain" wire:model="desainKode" :error="$errors->first('desainKode')" placeholder="mis. ERP-001" />
                            <x-input label="Tahun ajaran" wire:model="desainTahun" :error="$errors->first('desainTahun')" placeholder="mis. 2026/2027" />
                            <x-select label="Orientasi" wire:model="desainOrientasi" :options="\App\Models\Desain::ORIENTASI" :selected="$desainOrientasi" placeholder="— Tidak ditentukan —" />
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Foto desain</span>
                                <input type="file" wire:model="desainFoto" accept="image/*" class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-card file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
                                <div wire:loading wire:target="desainFoto" class="text-xs text-ink-muted">Mengunggah…</div>
                                @error('desainFoto')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-ink-muted">Ukuran yang berlaku diatur lewat chip ukuran setelah desain masuk daftar.</p>
                        <x-button type="button" wire:click="tambahDesainBaru" variant="secondary" size="sm" class="mt-3">
                            <span wire:loading.remove wire:target="tambahDesainBaru,desainFoto">Tambah ke daftar</span>
                            <span wire:loading wire:target="tambahDesainBaru,desainFoto">Menyiapkan…</span>
                        </x-button>
                    </div>
                </div>
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
