<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Profil</h1>
        <p class="text-sm text-ink-muted">Kelola informasi akun dan kata sandi Anda.</p>
    </x-slot>

    @php
        $roleLabel = $user->getRoleNames()->first();
        $roleLabel = $roleLabel ? \Illuminate\Support\Str::headline($roleLabel) : null;
        $cabangLabel = $user->seesAllCabang() ? 'Semua cabang' : ($user->cabang?->nama ?? 'Tanpa cabang');
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        {{-- Header identitas --}}
        <x-card>
            <div class="flex items-center gap-4">
                <x-avatar :name="$user->nama ?? $user->name" size="lg" />
                <div class="min-w-0">
                    <div class="truncate text-base font-medium text-ink">{{ $user->nama ?? $user->name }}</div>
                    <div class="truncate text-sm text-ink-muted">{{ $user->email }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-badge variant="brand">{{ $roleLabel ?? 'Tanpa peran' }}</x-badge>
                        <x-badge variant="navy">{{ $cabangLabel }}</x-badge>
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>
    </div>
</x-app-layout>
