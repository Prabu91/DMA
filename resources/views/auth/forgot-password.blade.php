<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Lupa kata sandi</h1>
        <p class="mt-1 text-sm text-ink-muted">Masukkan email Anda, kami kirimkan tautan untuk mengatur ulang kata sandi.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-input
            name="email"
            label="Email"
            type="email"
            :value="old('email')"
            required
            autofocus
            placeholder="nama@dma.test"
        />

        <x-button type="submit" class="w-full">Kirim tautan reset</x-button>

        <p class="pt-2 text-center text-sm text-ink-muted">
            <a href="{{ route('login') }}" class="font-medium text-brand hover:text-brand-hover">Kembali ke halaman masuk</a>
        </p>
    </form>
</x-guest-layout>
