<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Buat akun</h1>
        <p class="mt-1 text-sm text-ink-muted">Cabang &amp; peran akan diatur oleh admin setelah akun dibuat.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-input
            name="name"
            label="Nama lengkap"
            type="text"
            :value="old('name')"
            required
            autofocus
            autocomplete="name"
            placeholder="Nama Anda"
        />

        <x-input
            name="email"
            label="Email"
            type="email"
            :value="old('email')"
            required
            autocomplete="username"
            placeholder="nama@dma.test"
        />

        <x-input
            name="password"
            label="Kata sandi"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Minimal 8 karakter"
        />

        <x-input
            name="password_confirmation"
            label="Ulangi kata sandi"
            type="password"
            required
            autocomplete="new-password"
            placeholder="Ketik ulang kata sandi"
        />

        <x-button type="submit" class="w-full">Daftar</x-button>

        <p class="pt-2 text-center text-sm text-ink-muted">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-medium text-brand hover:text-brand-hover">Masuk</a>
        </p>
    </form>
</x-guest-layout>
