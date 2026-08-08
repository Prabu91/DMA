<x-storefront-layout>
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-14">
        <div class="rounded-xl border border-line bg-card p-6 shadow-sm sm:p-8">
            {{-- Logo --}}
            <div class="mb-6 flex justify-center">
                <img src="{{ asset('images/dma-logo.png') }}" alt="Delapan Mata Air" class="h-14 w-auto">
            </div>

            <h1 class="text-center text-xl font-extrabold tracking-tight text-ink">Registrasi sekolah baru</h1>
            <p class="mb-6 mt-1 text-center text-sm text-ink-muted">ID Sekolah dibuat otomatis oleh sistem.</p>

            <form method="POST" action="{{ route('sekolah.daftar.store') }}" class="space-y-4">
                @csrf

                <x-input name="nama" label="Nama sekolah" :value="old('nama')" required autofocus />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="pic_sekolah" label="Nama PIC" :value="old('pic_sekolah')" hint="Opsional." />
                    <x-input name="no_telp_pic" label="No. telepon PIC" :value="old('no_telp_pic')" hint="Opsional." />
                </div>

                <x-input name="alamat" label="Alamat" :value="old('alamat')" hint="Opsional." />

                {{-- Kota → cabang otomatis; "lainnya" = cabang diatur admin --}}
                <div x-data="{ kota: '{{ old('kota_id') }}' }" class="space-y-3">
                    <div class="space-y-1.5">
                        <label for="kota_id" class="block text-sm font-medium text-ink">Kota</label>
                        <select name="kota_id" id="kota_id" x-model="kota" required
                            class="block w-full min-h-[44px] rounded-lg border bg-card px-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand {{ $errors->has('kota_id') ? 'border-status-danger' : 'border-line' }}">
                            <option value="">— Pilih kota —</option>
                            @foreach ($kotaOptions as $k)
                                <option value="{{ $k->id }}" @selected(old('kota_id') == $k->id)>{{ $k->nama }}</option>
                            @endforeach
                            <option value="lainnya" @selected(old('kota_id') === 'lainnya')>Lainnya (kota tidak ada di daftar)</option>
                        </select>
                        @error('kota_id')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                    </div>

                    <div x-show="kota === 'lainnya'" x-cloak>
                        <x-input name="kota_lain" label="Tulis nama kota" :value="old('kota_lain')" :error="$errors->first('kota_lain')" hint="Cabang akan ditentukan admin." />
                    </div>
                </div>

                <x-input name="email" label="Email" type="email" :value="old('email')" required autocomplete="username" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-password-input name="password" label="Kata sandi" required autocomplete="new-password" placeholder="Min. 8 karakter" />
                    <x-password-input name="password_confirmation" label="Ulangi kata sandi" required autocomplete="new-password" />
                </div>

                {{-- Info ID Sekolah otomatis --}}
                <div class="border-l-[3px] border-status-info bg-status-info/10 px-3.5 py-2.5 text-xs leading-relaxed text-ink">
                    <b class="font-bold">ID Sekolah</b> otomatis dibuat setelah simpan, mis. <b class="font-bold">SKL-000241</b>. Simpan untuk login berikutnya.
                </div>

                <x-button type="submit" size="lg" class="w-full">Daftar &amp; buat ID Sekolah</x-button>
            </form>

            <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-muted">
                Sudah punya akun?
                <a href="{{ route('sekolah.masuk') }}" class="font-bold text-brand hover:text-brand-hover">Masuk</a>
            </p>
        </div>
    </div>
</x-storefront-layout>
