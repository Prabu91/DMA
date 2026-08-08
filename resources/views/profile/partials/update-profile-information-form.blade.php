@php
    $roleLabel = $user->getRoleNames()->first();
    $roleLabel = $roleLabel ? \Illuminate\Support\Str::headline($roleLabel) : 'Tanpa peran';
    $cabangLabel = $user->seesAllCabang() ? 'Semua cabang' : ($user->cabang?->nama ?? 'Tanpa cabang');
@endphp

<section>
    <header>
        <h2 class="text-base font-medium text-ink">Informasi profil</h2>
        <p class="mt-1 text-sm text-ink-muted">Perbarui nama, email, dan nomor telepon Anda.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('app.profile.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <x-input name="nama" label="Nama lengkap" :value="old('nama', $user->nama ?? $user->name)" required autofocus autocomplete="name" />

        <x-input name="email" label="Email" type="email" :value="old('email', $user->email)" required autocomplete="username" />

        <x-input name="no_telp" label="No. telepon" :value="old('no_telp', $user->no_telp)" placeholder="08xxxxxxxxxx" hint="Opsional." />

        {{-- Read-only: role & cabang diatur admin --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <span class="block text-sm font-medium text-ink">Peran</span>
                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">
                    {{ $roleLabel }}
                </div>
            </div>
            <div class="space-y-1.5">
                <span class="block text-sm font-medium text-ink">Cabang</span>
                <div class="flex min-h-[44px] items-center rounded-lg border border-line bg-page px-3 text-sm text-ink-muted">
                    {{ $cabangLabel }}
                </div>
            </div>
        </div>
        <p class="text-xs text-ink-muted">Peran dan cabang hanya dapat diubah oleh admin.</p>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="rounded-lg border border-status-pending/20 bg-status-pending/10 p-3">
                <p class="text-sm text-ink">
                    Email Anda belum terverifikasi.
                    <button form="send-verification" class="font-medium text-brand hover:text-brand-hover">
                        Kirim ulang tautan verifikasi.
                    </button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-status-success">Tautan verifikasi baru telah dikirim.</p>
                @endif
            </div>
        @endif

        <div class="flex items-center gap-4 pt-2">
            <x-button type="submit">Simpan</x-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm text-status-success">Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
