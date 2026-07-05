<div>
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

    <div class="mb-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama, ID, atau kota…" />
    </div>

    <x-card padding="p-0">
        @forelse ($sekolah as $item)
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate text-sm text-ink">{{ $item->nama }}</span>
                        <x-badge variant="neutral">{{ $item->id_sekolah }}</x-badge>
                        @if ($item->password)
                            <x-badge variant="success">Login aktif</x-badge>
                        @else
                            <x-badge variant="pending">Login belum diset</x-badge>
                        @endif
                    </div>
                    <div class="mt-0.5 truncate text-xs text-ink-muted">
                        {{ $item->kota ?: '—' }}
                        @if ($this->canChooseCabang)
                            · {{ $item->cabang?->nama ?? 'Tanpa cabang' }}
                        @endif
                        @if ($item->pic_sekolah)
                            · PIC: {{ $item->pic_sekolah }}
                        @endif
                    </div>
                    @if ($item->maps_link)
                        <a href="{{ $item->maps_link }}" target="_blank" rel="noopener"
                           class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                            Buka peta
                        </a>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $item->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-button wire:click="openResetPassword({{ $item->id }})" variant="ghost" size="sm">Reset sandi</x-button>
                    <x-button wire:click="delete({{ $item->id }})" wire:confirm="Hapus sekolah {{ $item->nama }}?" variant="ghost" size="sm">Hapus</x-button>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada sekolah.</div>
        @endforelse
    </x-card>

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
                        <x-input label="Link Google Maps" wire:model="maps_link" :error="$errors->first('maps_link')" placeholder="https://maps.google.com/…" hint="Tempel URL Google Maps." class="sm:col-span-2" />

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
                            <x-select label="Cabang" wire:model="cabang_id" :options="$this->cabangOptions" :selected="$cabang_id" placeholder="— Pilih cabang —" :error="$errors->first('cabang_id')" hint="ID sekolah dibuat otomatis." />
                        @else
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Cabang</span>
                                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">
                                    {{ auth()->user()->cabang?->nama ?? 'Tanpa cabang' }}
                                </div>
                                <p class="text-xs text-ink-muted">ID sekolah dibuat otomatis saat disimpan.</p>
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
