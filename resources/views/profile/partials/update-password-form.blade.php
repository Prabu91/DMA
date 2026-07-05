<section>
    <header>
        <h2 class="text-base font-medium text-ink">Kata sandi</h2>
        <p class="mt-1 text-sm text-ink-muted">Gunakan kata sandi yang panjang dan acak agar akun tetap aman.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('put')

        <x-input
            id="current_password"
            name="current_password"
            type="password"
            label="Kata sandi saat ini"
            autocomplete="current-password"
            :error="$errors->updatePassword->first('current_password')"
        />

        <x-input
            id="new_password"
            name="password"
            type="password"
            label="Kata sandi baru"
            autocomplete="new-password"
            :error="$errors->updatePassword->first('password')"
        />

        <x-input
            id="new_password_confirmation"
            name="password_confirmation"
            type="password"
            label="Ulangi kata sandi baru"
            autocomplete="new-password"
            :error="$errors->updatePassword->first('password_confirmation')"
        />

        <div class="flex items-center gap-4 pt-2">
            <x-button type="submit">Simpan</x-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm text-status-success">Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
