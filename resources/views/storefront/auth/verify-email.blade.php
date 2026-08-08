<x-storefront-layout>
    <div class="mx-auto max-w-md px-4 py-12 sm:py-16">
        <div class="mb-6 text-center">
            <span class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-xl bg-navy-900">
                <svg class="h-7 w-7 text-brand" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-ink">Verifikasi email</h1>
            <p class="mt-1 text-sm text-ink-muted">Kami mengirim tautan verifikasi ke email Anda. Klik untuk mengaktifkan akun.</p>
        </div>

        <x-card>
            @if (session('id_sekolah'))
                <div class="mb-4 rounded-lg border-l-[3px] border-brand bg-brand/10 px-4 py-3 text-center">
                    <div class="text-xs text-ink-muted">Kode akun sekolah Anda</div>
                    <div class="mt-1 text-lg font-extrabold tracking-wide text-brand-hover">{{ session('id_sekolah') }}</div>
                    <div class="mt-1 text-xs text-ink-muted">Simpan kode ini untuk referensi.</div>
                </div>
            @endif

            @if (session('status') === 'verification-link-sent')
                <div class="mb-4 rounded-lg border-l-[3px] border-status-success bg-status-success/10 px-4 py-3 text-sm font-semibold text-status-success">
                    Tautan verifikasi baru telah dikirim ke email Anda.
                </div>
            @endif

            <p class="text-sm text-ink-muted">Belum menerima email? Kami bisa kirim ulang.</p>

            <div class="mt-4 flex flex-col gap-3">
                <form method="POST" action="{{ route('sekolah.verification.send') }}">
                    @csrf
                    <x-button type="submit" class="w-full">Kirim ulang tautan</x-button>
                </form>
                <form method="POST" action="{{ route('sekolah.logout') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" class="w-full">Keluar</x-button>
                </form>
            </div>
        </x-card>
    </div>
</x-storefront-layout>
