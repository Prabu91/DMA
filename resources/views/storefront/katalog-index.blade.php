<x-storefront-layout>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        @if (session('id_sekolah'))
            <div class="mb-6 rounded-xl border-l-4 border-brand bg-brand/10 px-5 py-4">
                <p class="text-sm font-bold text-ink">Pendaftaran berhasil — selamat datang!</p>
                <p class="mt-1 text-sm text-ink-muted">
                    Gunakan ID ini untuk masuk berikutnya:
                    <span class="font-extrabold tracking-wide text-brand-hover">{{ session('id_sekolah') }}</span>.
                    Simpan baik-baik.
                </p>
            </div>
        @endif

        <livewire:katalog.etalase konteks="publik" />
    </div>
</x-storefront-layout>
