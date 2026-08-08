<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DMA') }} — Sekolah</title>

        @include('partials.favicon')

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="font-display antialiased">
        @php
            $sekolah = auth('sekolah')->user();
            $cartCount = app(\App\Support\Cart::class)->count();
        @endphp

        <div class="min-h-screen bg-page">
            <header class="sticky top-0 z-30 bg-navy-900 text-white">
                <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                    <a href="{{ route('storefront.home') }}" class="flex items-center gap-2.5">
                        <span class="inline-flex items-center rounded-lg bg-white px-2 py-1.5">
                            <img src="{{ asset('images/dma-mark.png') }}" alt="DMA" class="h-5 w-auto">
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm font-extrabold tracking-tight">Delapan Mata Air</span>
                            <span class="block text-[8px] font-semibold tracking-[0.16em] text-white/50">PORTAL SEKOLAH</span>
                        </span>
                    </a>

                    <div class="flex items-center gap-4 sm:gap-6">
                        <nav class="hidden items-center gap-6 text-sm font-semibold md:flex">
                            <a href="{{ route('storefront.katalog.index') }}" @class(['transition-colors hover:text-white', 'text-white' => request()->routeIs('storefront.katalog.*'), 'text-white/70' => ! request()->routeIs('storefront.katalog.*')])>Katalog</a>
                            <a href="{{ route('sekolah.riwayat.index') }}" @class(['transition-colors hover:text-white', 'text-white' => request()->routeIs('sekolah.riwayat.*'), 'text-white/70' => ! request()->routeIs('sekolah.riwayat.*')])>Riwayat</a>
                        </nav>

                        <div class="flex items-center gap-2">
                            {{-- Ikon keranjang + badge --}}
                            <a href="{{ route('sekolah.keranjang') }}"
                               class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                               aria-label="Keranjang">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('cart') }}" />
                                </svg>
                                @if ($cartCount > 0)
                                    <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.125rem] items-center justify-center rounded-full bg-brand px-1 text-[0.625rem] font-bold leading-tight text-white">{{ $cartCount }}</span>
                                @endif
                            </a>

                            @include('partials.account-menu')
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
                @include('layouts.flash')
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
