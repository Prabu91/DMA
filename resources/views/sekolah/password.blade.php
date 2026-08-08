<x-sekolah-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Ganti kata sandi</h1>
        <p class="text-sm text-ink-muted">Perbarui kata sandi login sekolah Anda.</p>
    </div>

    <div class="mx-auto max-w-lg">
        <x-card>
            <form method="POST" action="{{ route('sekolah.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-input
                    id="current_password"
                    name="current_password"
                    type="password"
                    label="Kata sandi saat ini"
                    autocomplete="current-password"
                    :error="$errors->first('current_password')"
                />
                <x-input
                    id="password"
                    name="password"
                    type="password"
                    label="Kata sandi baru"
                    autocomplete="new-password"
                    :error="$errors->first('password')"
                />
                <x-input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    label="Ulangi kata sandi baru"
                    autocomplete="new-password"
                />

                <div class="flex items-center gap-4 pt-2">
                    <x-button type="submit">Simpan</x-button>
                    @if (session('status') === 'password-updated')
                        <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                           class="text-sm text-status-success">Tersimpan.</p>
                    @endif
                </div>
            </form>
        </x-card>
    </div>
</x-sekolah-layout>
