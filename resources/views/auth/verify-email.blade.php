<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-medium text-ink">Verifikasi email</h1>
        <p class="mt-1 text-sm text-ink-muted">Terima kasih sudah mendaftar. Silakan verifikasi email Anda lewat tautan yang baru kami kirim. Belum menerima? Kami kirim ulang.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">
            Tautan verifikasi baru telah dikirim ke email Anda.
        </div>
    @endif

    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" class="w-full">Kirim ulang email verifikasi</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="secondary" class="w-full">Keluar</x-button>
        </form>
    </div>
</x-guest-layout>
