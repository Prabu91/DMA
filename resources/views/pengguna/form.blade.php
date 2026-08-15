{{-- Form pengguna dipakai create & edit. Variabel: $pengguna (opsional), $cabangOptions, $roleOptions, $kecamatanByCabang, $selectedKecamatan, $pusatRoles --}}
@php $pengguna = $pengguna ?? null; @endphp

<x-card>
    <form method="POST" action="{{ $pengguna ? route('app.pengguna.update', $pengguna) : route('app.pengguna.store') }}"
        class="max-w-lg space-y-4"
        x-data="{
            role: '{{ old('role', $pengguna?->getRoleNames()->first()) }}',
            cabang: '{{ old('cabang_id', $pengguna?->cabang_id) }}',
            kecById: {{ Illuminate\Support\Js::from($kecamatanByCabang) }},
            selected: {{ Illuminate\Support\Js::from(array_map('strval', $selectedKecamatan)) }},
            pusatRoles: {{ Illuminate\Support\Js::from($pusatRoles) }},
            get opsiKecamatan() { return this.kecById[this.cabang] || [] },
            get terpusat() { return this.pusatRoles.includes(this.role) },
        }">
        @csrf
        @if ($pengguna)
            @method('PATCH')
        @endif

        <x-input name="nama" label="Nama lengkap" :value="old('nama', $pengguna?->nama ?? $pengguna?->name)" required autofocus autocomplete="name" />

        <x-input name="email" label="Email" type="email" :value="old('email', $pengguna?->email)" required autocomplete="off" />

        <x-input name="no_telp" label="No. telepon" :value="old('no_telp', $pengguna?->no_telp)" hint="Opsional." placeholder="08xxxxxxxxxx" />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-select
                name="role"
                label="Peran"
                x-model="role"
                :options="$roleOptions"
                :selected="old('role', $pengguna?->getRoleNames()->first())"
                placeholder="— Tanpa peran —"
                hint="Menentukan akses."
            />
            {{-- Cabang: role terpusat → paksa "Semua cabang"; selain itu pilih satu cabang --}}
            <div>
                <div x-show="!terpusat">
                    <x-select
                        name="cabang_id"
                        label="Cabang"
                        x-model="cabang"
                        :options="$cabangOptions"
                        :selected="old('cabang_id', $pengguna?->cabang_id)"
                        placeholder="— Pilih cabang —"
                        hint="Wajib untuk marketing & tim event."
                    />
                </div>
                <div x-show="terpusat" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-ink">Cabang</label>
                    <div class="flex items-center gap-2 rounded-lg border border-line bg-surface-muted px-3 py-2 text-sm text-ink">
                        <span class="inline-flex items-center rounded-full bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand">Semua cabang</span>
                        <span class="text-ink-muted">Akun terpusat — akses lintas cabang.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kecamatan yang ditangani (khusus marketing; dari cabang terpilih) --}}
        <div x-show="role === 'marketing'" x-cloak class="border-t border-line pt-4">
            <p class="mb-1 text-sm font-medium text-ink">Kecamatan yang ditangani</p>
            <p class="mb-3 text-xs text-ink-muted">Order sekolah dari kecamatan ini otomatis diarahkan ke marketing ini.</p>

            <template x-if="!cabang">
                <p class="text-sm text-ink-muted">Pilih cabang dulu.</p>
            </template>
            <template x-if="cabang && !opsiKecamatan.length">
                <p class="text-sm text-ink-muted">Belum ada kecamatan untuk cabang ini.</p>
            </template>

            <div x-show="cabang && opsiKecamatan.length" class="grid gap-2 sm:grid-cols-2">
                <template x-for="kec in opsiKecamatan" :key="kec.id">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="kecamatan_ids[]" :value="kec.id"
                            :checked="selected.includes(String(kec.id))"
                            class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                        <span x-text="kec.label"></span>
                    </label>
                </template>
            </div>
            @error('kecamatan_ids')<p class="mt-1 text-xs text-status-danger">{{ $message }}</p>@enderror
        </div>

        <div class="border-t border-line pt-4">
            <p class="mb-3 text-sm font-medium text-ink">
                {{ $pengguna ? 'Ubah kata sandi' : 'Kata sandi' }}
            </p>
            <div class="space-y-4">
                <x-input
                    name="password"
                    label="Kata sandi{{ $pengguna ? ' baru' : '' }}"
                    type="password"
                    :required="! $pengguna"
                    autocomplete="new-password"
                    :hint="$pengguna ? 'Kosongkan jika tidak diubah.' : 'Minimal 8 karakter.'"
                />
                <x-input
                    name="password_confirmation"
                    label="Ulangi kata sandi"
                    type="password"
                    :required="! $pengguna"
                    autocomplete="new-password"
                />
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-button type="submit">{{ $pengguna ? 'Simpan perubahan' : 'Simpan' }}</x-button>
            <x-button :href="route('app.pengguna.index')" variant="ghost">Batal</x-button>
        </div>
    </form>
</x-card>
