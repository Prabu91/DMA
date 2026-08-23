@php
    $user = auth()->user();
    $roleLabel = $user?->getRoleNames()->first();
    $roleLabel = $roleLabel ? \Illuminate\Support\Str::headline($roleLabel) : 'Tanpa peran';
    // Indikator cabang: super_admin & operasional lihat semua cabang.
    $cabangLabel = $user?->seesAllCabang()
        ? 'Semua cabang'
        : ($user?->cabang?->nama ?? 'Tanpa cabang');
@endphp

<header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-card/95 px-4 backdrop-blur sm:px-6">
    {{-- Hamburger: buka drawer menu di mobile --}}
    <button type="button" x-on:click="sidebarOpen = true"
            class="-ml-1 rounded-lg p-2 text-ink-muted transition-colors hover:bg-page hover:text-ink lg:hidden"
            aria-label="Buka menu">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
        </svg>
    </button>

    {{-- Logo untuk mobile (sidebar disembunyikan) --}}
    <a href="{{ route('app.dashboard') }}" class="lg:hidden">
        <x-brand-logo size="sm" :wordmark="false" />
    </a>

    {{-- Indikator cabang — selalu terlihat (sistem multi-cabang) --}}
    <span class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-page px-2.5 py-1.5 text-xs font-medium text-ink">
        <svg class="h-4 w-4 text-navy" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('building') }}" />
        </svg>
        <span class="max-w-[9rem] truncate">{{ $cabangLabel }}</span>
    </span>

    {{-- Menu user --}}
    <div class="ml-auto">
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-lg px-1.5 py-1.5 text-sm transition-colors hover:bg-page">
                    <x-avatar :name="$user?->nama ?? $user?->name" size="sm" />
                    <span class="hidden text-start leading-tight sm:block">
                        <span class="block font-medium text-ink">{{ $user?->nama ?? $user?->name }}</span>
                        <span class="block text-xs text-ink-muted">{{ $roleLabel }}</span>
                    </span>
                    <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="border-b border-line px-4 py-3">
                    <div class="text-sm font-medium text-ink">{{ $user?->nama ?? $user?->name }}</div>
                    <div class="truncate text-xs text-ink-muted">{{ $user?->email }}</div>
                </div>
                <x-dropdown-link :href="route('app.profile.edit')">Profil</x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Keluar
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
