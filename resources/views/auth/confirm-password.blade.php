<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Konfirmasi kata sandi</h1>
        <p class="mt-1 text-sm text-ink-muted">Ini area aman. Masukkan kata sandi Anda untuk melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-input
            name="password"
            label="Kata sandi"
            type="password"
            required
            autocomplete="current-password"
        />

        <x-button type="submit" class="w-full">Konfirmasi</x-button>
    </form>
</x-guest-layout>
