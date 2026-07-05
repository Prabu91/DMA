<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Masuk ke akun</h1>
        <p class="mt-1 text-sm text-ink-muted">Selamat datang kembali. Masuk untuk melanjutkan.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input
            name="email"
            label="Email"
            type="email"
            :value="old('email')"
            required
            autofocus
            autocomplete="username"
            placeholder="nama@dma.test"
        />

        <x-input
            name="password"
            label="Kata sandi"
            type="password"
            required
            autocomplete="current-password"
            placeholder="••••••••"
        />

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember"
                       class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                <span class="text-sm text-ink-muted">Ingat saya</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand hover:text-brand-hover">
                    Lupa kata sandi?
                </a>
            @endif
        </div>

        <x-button type="submit" class="w-full">Masuk</x-button>

        @if (Route::has('register'))
            <p class="pt-2 text-center text-sm text-ink-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-medium text-brand hover:text-brand-hover">Daftar</a>
            </p>
        @endif
    </form>
</x-guest-layout>
