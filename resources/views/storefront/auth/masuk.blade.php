<x-storefront-layout>
    <div class="mx-auto max-w-md px-4 py-10 sm:py-14">
        <div class="rounded-xl border border-line bg-card p-6 shadow-sm sm:p-8">
            {{-- Logo --}}
            <div class="mb-6 flex justify-center">
                <img src="{{ asset('images/dma-logo.png') }}" alt="Delapan Mata Air" class="h-14 w-auto">
            </div>

            <h1 class="text-center text-xl font-extrabold tracking-tight text-ink">Masuk ke akun sekolah</h1>
            <p class="mb-6 mt-1 text-center text-sm text-ink-muted">Gunakan ID sekolah &amp; kata sandi yang terdaftar.</p>

            <form method="POST" action="{{ route('sekolah.masuk.store') }}" class="space-y-4">
                @csrf

                <x-input name="id_sekolah" label="ID Sekolah" :value="old('id_sekolah')" required autofocus placeholder="SKL-000001" autocomplete="username" />
                <x-password-input name="password" label="Kata sandi" required autocomplete="current-password" />

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                    <span class="text-sm text-ink-muted">Ingat saya</span>
                </label>

                <x-button type="submit" size="lg" class="w-full">Masuk</x-button>
            </form>

            <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-muted">
                Belum punya akun?
                <a href="{{ route('sekolah.daftar') }}" class="font-bold text-brand hover:text-brand-hover">Daftar sekarang</a>
            </p>
        </div>

        <p class="mt-5 text-center text-xs text-ink-muted">
            Tim DMA? <a href="{{ route('login') }}" class="font-bold text-brand hover:text-brand-hover">Masuk ke panel staf</a>.
        </p>
    </div>
</x-storefront-layout>
