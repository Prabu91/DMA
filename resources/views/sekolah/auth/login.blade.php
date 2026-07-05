<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Masuk sekolah</h1>
        <p class="mt-1 text-sm text-ink-muted">Masuk dengan ID sekolah dan kata sandi Anda untuk melakukan booking.</p>
    </div>

    <form method="POST" action="{{ route('sekolah.login.store') }}" class="space-y-4">
        @csrf

        <x-input
            name="id_sekolah"
            label="ID sekolah"
            :value="old('id_sekolah')"
            required
            autofocus
            placeholder="mis. SKL-JKT-0001"
        />

        <x-input
            name="password"
            label="Kata sandi"
            type="password"
            required
            autocomplete="current-password"
        />

        <label class="flex items-center gap-2">
            <input type="checkbox" name="remember"
                   class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
            <span class="text-sm text-ink-muted">Ingat saya</span>
        </label>

        <x-button type="submit" class="w-full">Masuk</x-button>

        <p class="pt-2 text-center text-xs text-ink-muted">
            Belum punya akses? Hubungi tim marketing DMA cabang Anda.
        </p>
    </form>
</x-guest-layout>
