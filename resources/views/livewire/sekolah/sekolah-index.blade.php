<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Sekolah'],
    ]" />

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-medium text-ink">Sekolah</h1>
            <p class="text-sm text-ink-muted">
                @if ($this->canChooseCabang)
                    Data sekolah seluruh cabang.
                @else
                    Data sekolah cabang Anda.
                @endif
            </p>
        </div>
        <x-button wire:click="create" size="sm">Tambah sekolah</x-button>
    </div>

    @if ($success)
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ $success }}</div>
    @endif
    @if ($error)
        <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="min-w-[200px] flex-1">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama, ID, atau kota…" />
        </div>
        @if ($this->canChooseCabang)
            <select wire:model.live="filterCabang"
                    class="h-11 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                <option value="">Semua cabang</option>
                @foreach ($this->cabangOptions as $id => $nama)<option value="{{ $id }}" @selected($filterCabang === (string) $id)>{{ $nama }}</option>@endforeach
            </select>
        @endif
        <select wire:model.live="filterKategori"
                class="h-11 rounded-lg border border-line bg-card pl-2.5 pr-9 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
            <option value="">Semua kategori</option>
            <option value="NOS">NOS · belum pernah</option>
            <option value="NRS">NRS · 1–2 order</option>
            <option value="SR">SR · setia (≥3)</option>
        </select>
    </div>

    {{-- Mobile: kartu ringkas --}}
    <div class="space-y-2 md:hidden">
        @forelse ($sekolah as $item)
            @php $kat = $item->kategoriPelanggan(); @endphp
            <div class="rounded-xl border border-line bg-card p-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-ink">{{ $item->nama }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-md bg-ink/5 px-1.5 py-0.5 text-xs text-ink-muted">{{ $item->id_sekolah }}</span>
                            @if ($item->maps_link)
                                <a href="{{ $item->maps_link }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>Peta
                                </a>
                            @endif
                        </div>
                    </div>
                    <x-badge :variant="\App\Models\Sekolah::kategoriBadge($kat)">{{ $kat }}</x-badge>
                </div>
                <div class="mt-2 text-xs text-ink-muted">
                    {{ $item->kota ?: '—' }}@if ($item->kecamatan) · Kec. {{ $item->kecamatan->nama }}@endif @if ($this->canChooseCabang)· {{ $item->cabang?->nama ?? 'Tanpa cabang' }}@endif
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                    <span>PIC: {{ $item->pic_sekolah ?: '—' }}</span>
                    <span class="inline-flex items-center gap-1">Login: @if ($item->password)<x-badge variant="success">Aktif</x-badge>@else<x-badge variant="pending">Belum diset</x-badge>@endif</span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="openResetPassword({{ $item->id }})" variant="ghost" size="sm">Reset sandi</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus sekolah {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-line bg-card px-4 py-10 text-center text-sm text-ink-muted">Belum ada sekolah.</div>
        @endforelse
    </div>

    {{-- Desktop: tabel penuh --}}
    <div class="hidden md:block">
    <x-table min-width="960px">
        <x-slot:head>
            <x-table.th sortable field="nama" :sort="$sortField" :dir="$sortDir">Sekolah</x-table.th>
            <x-table.th sortable field="kategori" :sort="$sortField" :dir="$sortDir">Kategori</x-table.th>
            <x-table.th sortable field="kota" :sort="$sortField" :dir="$sortDir">Wilayah</x-table.th>
            <x-table.th>PIC</x-table.th>
            <x-table.th>Login</x-table.th>
            <x-table.th align="right">Aksi</x-table.th>
        </x-slot:head>

        @forelse ($sekolah as $item)
            <x-table.tr>
                <x-table.td>
                    <div class="font-medium text-ink">{{ $item->nama }}</div>
                    <div class="mt-0.5 flex items-center gap-2">
                        <span class="rounded-md bg-ink/5 px-1.5 py-0.5 text-xs text-ink-muted">{{ $item->id_sekolah }}</span>
                        @if ($item->maps_link)
                            <a href="{{ $item->maps_link }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                Peta
                            </a>
                        @endif
                    </div>
                </x-table.td>
                <x-table.td>
                    @php $kat = $item->kategoriPelanggan(); @endphp
                    <x-badge :variant="\App\Models\Sekolah::kategoriBadge($kat)" title="{{ $item->dealCount() }} order selesai">{{ $kat }}</x-badge>
                </x-table.td>
                <x-table.td muted>
                    <div class="text-ink">{{ $item->kota ?: '—' }}</div>
                    <div class="text-xs text-ink-muted">
                        @if ($item->kecamatan)Kec. {{ $item->kecamatan->nama }}@endif
                        @if ($this->canChooseCabang) · {{ $item->cabang?->nama ?? 'Tanpa cabang' }}@endif
                    </div>
                </x-table.td>
                <x-table.td muted>{{ $item->pic_sekolah ?: '—' }}</x-table.td>
                <x-table.td>
                    @if ($item->password)
                        <x-badge variant="success">Aktif</x-badge>
                    @else
                        <x-badge variant="pending">Belum diset</x-badge>
                    @endif
                </x-table.td>
                <x-table.td align="right" nowrap>
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="openResetPassword({{ $item->id }})" variant="ghost" size="sm">Reset sandi</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus sekolah {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </x-table.td>
            </x-table.tr>
        @empty
            <x-table.empty :colspan="6">Belum ada sekolah.</x-table.empty>
        @endforelse
    </x-table>
    </div>

    {{-- Modal form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="sekolah-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah sekolah' : 'Tambah sekolah' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Nama sekolah" wire:model="nama" :error="$errors->first('nama')" class="sm:col-span-2" />
                        <x-input label="Kota" wire:model="kota" :error="$errors->first('kota')" />
                        <x-input label="PIC sekolah" wire:model="pic_sekolah" :error="$errors->first('pic_sekolah')" />
                        <x-input label="No. telepon PIC" wire:model="no_telp_pic" :error="$errors->first('no_telp_pic')" />
                        <x-input label="Email guru" type="email" wire:model="email_guru" :error="$errors->first('email_guru')" />
                        <x-input label="Alamat" wire:model="alamat" :error="$errors->first('alamat')" class="sm:col-span-2" />
                        <div class="space-y-1.5 sm:col-span-2">
                            <div class="flex items-center justify-between gap-2">
                                <label for="maps_link" class="block text-sm font-medium text-ink">Link Google Maps</label>
                                <button type="button" wire:click="generateMapsLink"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                    Buat dari nama &amp; alamat
                                </button>
                            </div>
                            <input id="maps_link" type="url" wire:model="maps_link" placeholder="https://maps.google.com/…"
                                   class="block min-h-[44px] w-full rounded-lg border {{ $errors->has('maps_link') ? 'border-status-danger' : 'border-line' }} bg-card px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                            @error('maps_link')
                                <p class="text-xs text-status-danger">{{ $message }}</p>
                            @else
                                <p class="text-xs text-ink-muted">Otomatis dibuat dari nama &amp; alamat bila dikosongkan — atau tempel URL sendiri.</p>
                            @enderror
                        </div>

                        {{-- Cabang --}}
                        @if ($editingId)
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">ID sekolah</span>
                                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">{{ $idSekolahPreview }}</div>
                            </div>
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Cabang</span>
                                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">
                                    {{ $this->cabangOptions[$cabang_id] ?? 'Tanpa cabang' }}
                                </div>
                            </div>
                        @elseif ($this->canChooseCabang)
                            <x-select label="Cabang" wire:model.live="cabang_id" :options="$this->cabangOptions" :selected="$cabang_id" placeholder="— Pilih cabang —" :error="$errors->first('cabang_id')" hint="ID sekolah dibuat otomatis." />
                        @else
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Cabang</span>
                                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">
                                    {{ auth()->user()->cabang?->nama ?? 'Tanpa cabang' }}
                                </div>
                                <p class="text-xs text-ink-muted">ID sekolah dibuat otomatis saat disimpan.</p>
                            </div>
                        @endif

                        {{-- Kecamatan (untuk auto-assign marketing per wilayah) --}}
                        @if (! empty($this->kecamatanOptions))
                            <x-searchable-select wire:key="kec-{{ $cabang_id }}" label="Kecamatan" model="kecamatan_id" :options="$this->kecamatanOptions" :selected="$kecamatan_id" placeholder="— Pilih kecamatan —" :error="$errors->first('kecamatan_id')" hint="Menentukan marketing wilayah. Bisa dicari." />
                        @else
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Kecamatan</span>
                                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">Belum ada kecamatan untuk cabang ini.</div>
                            </div>
                        @endif
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

    {{-- Modal reset password login sekolah --}}
    @if ($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="sekolah-password-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showPasswordModal', false)"></div>
            <div class="relative w-full max-w-md rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">Reset kata sandi login</h2>
                <p class="mt-1 text-sm text-ink-muted">Untuk sekolah <span class="text-ink">{{ $passwordSekolahNama }}</span>. Sekolah dapat mengganti sendiri setelah login.</p>

                <form wire:submit="savePassword" class="mt-4 space-y-4">
                    <x-input id="reset-password" label="Kata sandi baru" type="password" wire:model="newPassword" :error="$errors->first('newPassword')" />
                    <x-input id="reset-password-confirm" label="Ulangi kata sandi" type="password" wire:model="newPassword_confirmation" />

                    <div class="flex items-center gap-3 pt-2">
                        <x-button type="submit">
                            <span wire:loading.remove wire:target="savePassword">Simpan kata sandi</span>
                            <span wire:loading wire:target="savePassword">Menyimpan…</span>
                        </x-button>
                        <x-button type="button" wire:click="$set('showPasswordModal', false)" variant="ghost">Batal</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
