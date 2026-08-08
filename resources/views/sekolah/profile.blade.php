<x-sekolah-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Profil sekolah</h1>
        <p class="text-sm text-ink-muted">Kelola data kontak sekolah Anda.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Identitas akun (read-only) --}}
        <div class="lg:col-span-1">
            <x-card title="Identitas akun">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-ink-muted">ID sekolah</dt>
                        <dd class="text-ink">{{ $sekolah->id_sekolah }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Email login</dt>
                        <dd class="break-all text-ink">{{ $sekolah->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Kota</dt>
                        <dd class="text-ink">{{ $sekolah->kota ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Cabang</dt>
                        <dd>
                            @if ($sekolah->cabang)
                                <x-badge variant="brand">{{ $sekolah->cabang->nama }}</x-badge>
                            @else
                                <x-badge variant="pending">Belum ditetapkan</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>
                @unless ($sekolah->cabang)
                    <p class="mt-3 border-t border-line pt-3 text-xs text-ink-muted">
                        Cabang ditetapkan oleh admin. Hubungi admin agar pesanan dapat diproses.
                    </p>
                @endunless
            </x-card>
        </div>

        {{-- Form data kontak --}}
        <div class="lg:col-span-2">
            <x-card>
                <form method="POST" action="{{ route('sekolah.profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <x-input name="nama" label="Nama sekolah" :value="old('nama', $sekolah->nama)" :error="$errors->first('nama')" />
                    <x-input name="pic_sekolah" label="PIC / narahubung" :value="old('pic_sekolah', $sekolah->pic_sekolah)" :error="$errors->first('pic_sekolah')" />
                    <x-input name="no_telp_pic" label="No. telepon PIC" :value="old('no_telp_pic', $sekolah->no_telp_pic)" :error="$errors->first('no_telp_pic')" />
                    <div class="rounded-lg border border-line bg-page/50 px-3 py-2 text-xs text-ink-muted">
                        Notifikasi (verifikasi &amp; kode OTP event) dikirim ke email login Anda: <span class="font-medium text-ink">{{ $sekolah->email }}</span>
                    </div>
                    <x-input name="alamat" label="Alamat" :value="old('alamat', $sekolah->alamat)" :error="$errors->first('alamat')" />
                    <x-input name="maps_link" type="url" label="Tautan Google Maps" :value="old('maps_link', $sekolah->maps_link)" :error="$errors->first('maps_link')" placeholder="https://maps.google.com/…" />

                    <div class="flex items-center gap-4 pt-2">
                        <x-button type="submit">Simpan perubahan</x-button>
                        @if (session('status') === 'profile-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                               class="text-sm text-status-success">Tersimpan.</p>
                        @endif
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-sekolah-layout>
