<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Data master'],
        ['label' => 'Pengguna'],
    ]" />

    <x-toast :success="$success" :error="$error" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-medium text-ink">Pengguna</h1>
            <p class="text-sm text-ink-muted">Akun staf beserta cabang &amp; wilayah yang dipegang.</p>
        </div>
        <x-button wire:click="create" size="sm" class="shrink-0 self-start whitespace-nowrap sm:self-auto">Tambah pengguna</x-button>
    </div>

    {{-- Filter tetap terpasang saat menambah/mengubah, karena formnya modal --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau email…" />
        <x-select wire:model.live="filterCabang" :options="$this->cabangOptions" :selected="$filterCabang" placeholder="Semua cabang" />
        <x-select wire:model.live="filterRole" :options="$this->roleOptions" :selected="$filterRole" placeholder="Semua role" />
    </div>

    <x-card padding="p-0">
        @forelse ($pengguna as $u)
            <div wire:key="u-{{ $u->id }}" class="flex flex-col gap-2 border-b border-line px-5 py-3.5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-ink">{{ $u->nama ?: $u->name }}</span>
                        @foreach ($u->roles as $r)
                            <x-badge variant="navy">{{ \Illuminate\Support\Str::headline($r->name) }}</x-badge>
                        @endforeach
                    </div>
                    <div class="mt-0.5 text-xs text-ink-muted">
                        {{ $u->email }}@if ($u->no_telp) · {{ $u->no_telp }}@endif
                    </div>
                    <div class="mt-1 text-xs text-ink-muted">
                        @if ($u->seesAllCabang())
                            Semua cabang
                        @else
                            @php
                                $namaCabang = $u->cabangs->pluck('nama');
                                if ($namaCabang->isEmpty() && $u->cabang) {
                                    $namaCabang = collect([$u->cabang->nama]);
                                }
                            @endphp
                            {{ $namaCabang->isNotEmpty() ? $namaCabang->join(' · ') : 'Tanpa cabang' }}
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-button wire:click="edit({{ $u->id }})" variant="secondary" size="sm">Ubah</x-button>
                    <x-confirm action="delete" :arg="$u->id" variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, hapus"
                               title="Hapus pengguna" message="Akun {{ $u->nama ?: $u->name }} akan dihapus permanen. Lanjutkan?">Hapus</x-confirm>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Tidak ada pengguna yang cocok.</div>
        @endforelse
    </x-card>

    <x-table-footer :paginator="$pengguna" />

    {{-- Form modal: halaman tidak dimuat ulang, filter tetap terpasang --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="pengguna-modal">
            <div class="absolute inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-t-xl border border-line bg-card p-5 shadow-lg sm:rounded-xl">
                <h2 class="text-base font-medium text-ink">{{ $editingId ? 'Ubah pengguna' : 'Tambah pengguna' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Nama" wire:model="nama" :error="$errors->first('nama')" />
                        <x-input label="Email" type="email" wire:model="email" :error="$errors->first('email')" />
                        <x-input label="No. telepon" wire:model="no_telp" :error="$errors->first('no_telp')" hint="Dipakai notifikasi WhatsApp." />
                        <x-select label="Role" wire:model.live="role" :options="$this->roleOptions" :selected="$role" placeholder="— Tanpa role —" :error="$errors->first('role')" />
                    </div>

                    {{-- Cabang: sederajat, boleh lebih dari satu --}}
                    <div class="space-y-1.5">
                        <span class="block text-sm font-medium text-ink">Cabang</span>
                        @if ($this->roleLintasCabang)
                            <p class="rounded-lg border border-line bg-page px-3 py-2.5 text-sm text-ink-muted">
                                Role ini melihat <span class="font-medium text-ink">semua cabang</span>, jadi tidak perlu ditugaskan.
                            </p>
                        @else
                            <div class="grid gap-2 rounded-lg border border-line p-3 sm:grid-cols-2">
                                @foreach ($this->cabangOptions as $id => $nama)
                                    <label class="flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" value="{{ $id }}" wire:model.live="cabangIds"
                                               class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                        {{ $nama }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-ink-muted">Boleh lebih dari satu; semuanya sederajat.</p>
                            @error('cabangIds')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    {{-- Kecamatan hanya relevan untuk marketing --}}
                    @if ($role === 'marketing')
                        <div class="space-y-1.5">
                            <span class="block text-sm font-medium text-ink">Kecamatan yang ditangani</span>
                            @if (empty($this->kecamatanOptions))
                                <p class="rounded-lg border border-line bg-page px-3 py-2.5 text-sm text-ink-muted">
                                    Pilih cabang dulu, atau semua kecamatannya sudah dipegang marketing lain.
                                </p>
                            @else
                                <div class="grid max-h-52 gap-2 overflow-y-auto rounded-lg border border-line p-3 sm:grid-cols-2">
                                    @foreach ($this->kecamatanOptions as $id => $label)
                                        <label class="flex items-center gap-2 text-sm text-ink">
                                            <input type="checkbox" value="{{ $id }}" wire:model="kecamatanIds"
                                                   class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-ink-muted">Satu kecamatan hanya boleh dipegang satu marketing.</p>
                            @endif
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="{{ $editingId ? 'Kata sandi baru' : 'Kata sandi' }}" type="password" wire:model="password"
                                 :error="$errors->first('password')" hint="{{ $editingId ? 'Kosongkan bila tidak diubah.' : '' }}" />
                        <x-input label="Ulangi kata sandi" type="password" wire:model="password_confirmation" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <x-button type="submit">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </x-button>
                        <x-button type="button" variant="ghost" wire:click="$set('showForm', false)">Batal</x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
