<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DMA') }} — Sekolah</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        @php $sekolah = auth('sekolah')->user(); @endphp

        <div class="min-h-screen bg-page">
            <header class="sticky top-0 z-30 border-b border-line bg-card">
                <div class="mx-auto flex h-16 max-w-4xl items-center justify-between gap-3 px-4 sm:px-6">
                    <a href="{{ route('sekolah.beranda') }}">
                        <x-brand-logo size="sm" />
                    </a>

                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 rounded-lg px-1.5 py-1.5 text-sm transition-colors hover:bg-page">
                                <x-avatar :name="$sekolah?->nama" size="sm" />
                                <span class="hidden text-start leading-tight sm:block">
                                    <span class="block max-w-[12rem] truncate font-medium text-ink">{{ $sekolah?->nama }}</span>
                                    <span class="block text-xs text-ink-muted">{{ $sekolah?->id_sekolah }}</span>
                                </span>
                                <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="border-b border-line px-4 py-3">
                                <div class="truncate text-sm font-medium text-ink">{{ $sekolah?->nama }}</div>
                                <div class="text-xs text-ink-muted">{{ $sekolah?->id_sekolah }}</div>
                            </div>
                            <x-dropdown-link :href="route('sekolah.beranda')">Beranda</x-dropdown-link>
                            <x-dropdown-link :href="route('sekolah.katalog.index')">Katalog</x-dropdown-link>
                            <x-dropdown-link :href="route('sekolah.riwayat.index')">Riwayat booking</x-dropdown-link>
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
                </div>
            </header>

            <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6">
                @include('layouts.flash')
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
