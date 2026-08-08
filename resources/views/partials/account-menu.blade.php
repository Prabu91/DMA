{{-- Dropdown akun sekolah — dipakai di navbar storefront & portal. --}}
@php $sekolah = auth('sekolah')->user(); @endphp

<x-dropdown align="right" width="56">
    <x-slot name="trigger">
        <button class="flex items-center gap-2 rounded-lg px-1.5 py-1.5 text-sm transition-colors hover:bg-white/10">
            <x-avatar :name="$sekolah?->nama" size="sm" />
            <span class="hidden text-start leading-tight sm:block">
                <span class="block max-w-[12rem] truncate font-bold text-white">{{ $sekolah?->nama }}</span>
                <span class="block text-xs text-white/55">{{ $sekolah?->id_sekolah }}</span>
            </span>
            <svg class="h-4 w-4 text-white/60" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="border-b border-line px-4 py-3">
            <div class="truncate text-sm font-medium text-ink">{{ $sekolah?->nama }}</div>
            <div class="text-xs text-ink-muted">{{ $sekolah?->id_sekolah }}</div>
        </div>

        {{-- Katalog & Riwayat: di dropdown hanya pada mobile (di desktop sudah ada di navbar). --}}
        <div class="sm:hidden">
            <x-dropdown-link :href="route('storefront.katalog.index')">Katalog</x-dropdown-link>
            <x-dropdown-link :href="route('sekolah.riwayat.index')">Riwayat booking</x-dropdown-link>
            <div class="my-1 border-t border-line"></div>
        </div>

        <x-dropdown-link :href="route('sekolah.profile.edit')">Profil sekolah</x-dropdown-link>
        <x-dropdown-link :href="route('sekolah.password.edit')">Ganti kata sandi</x-dropdown-link>
        <form method="POST" action="{{ route('sekolah.logout') }}">
            @csrf
            <x-dropdown-link :href="route('sekolah.logout')"
                onclick="event.preventDefault(); this.closest('form').submit();">
                Keluar
            </x-dropdown-link>
        </form>
    </x-slot>
</x-dropdown>
