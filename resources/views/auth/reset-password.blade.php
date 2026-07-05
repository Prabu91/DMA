<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Atur ulang kata sandi</h1>
        <p class="mt-1 text-sm text-ink-muted">Buat kata sandi baru untuk akun Anda.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input
            name="email"
            label="Email"
            type="email"
            :value="old('email', $request->email)"
            required
            autofocus
            autocomplete="username"
        />

        <x-input
            name="password"
            label="Kata sandi baru"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Minimal 8 karakter"
        />

        <x-input
            name="password_confirmation"
            label="Ulangi kata sandi baru"
            type="password"
            required
            autocomplete="new-password"
        />

        <x-button type="submit" class="w-full">Simpan kata sandi</x-button>
    </form>
</x-guest-layout>
