<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Delapan Mata Air — Studio Foto' }}</title>

        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#191B52">
        @include('partials.favicon')

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{display:none!important}</style>
    </head>
    {{-- Storefront: modernist, navy + oranye, e-commerce, mobile-first. --}}
    <body class="font-display antialiased">
        @php
            $sekolah = auth('sekolah')->user();
            $cartCount = app(\App\Support\Cart::class)->count();
        @endphp

        <div class="flex min-h-screen flex-col bg-page">
            {{-- Top bar storefront (navy, e-commerce) --}}
            <header class="sticky top-0 z-30 bg-navy-900 text-white">
                <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                    <a href="{{ route('storefront.home') }}" class="flex items-center gap-2.5">
                        <span class="inline-flex items-center rounded-lg bg-white px-2 py-1.5">
                            <img src="{{ asset('images/dma-mark.png') }}" alt="DMA" class="h-5 w-auto">
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm font-extrabold tracking-tight">Delapan Mata Air</span>
                            <span class="block text-[8px] font-semibold tracking-[0.16em] text-white/50">STUDIO FOTO</span>
                        </span>
                    </a>

                    <div class="flex items-center gap-4 sm:gap-6">
                        <nav class="hidden items-center gap-6 text-sm font-semibold md:flex">
                            <a href="{{ route('storefront.katalog.index') }}" @class(['transition-colors hover:text-white', 'text-white' => request()->routeIs('storefront.katalog.*'), 'text-white/70' => ! request()->routeIs('storefront.katalog.*')])>Katalog</a>
                            @if ($sekolah)
                                <a href="{{ route('sekolah.riwayat.index') }}" @class(['transition-colors hover:text-white', 'text-white' => request()->routeIs('sekolah.riwayat.*'), 'text-white/70' => ! request()->routeIs('sekolah.riwayat.*')])>Riwayat</a>
                            @endif
                        </nav>

                        <div class="flex items-center gap-2">
            {{-- Ikon keranjang + badge jumlah (reaktif ke event cart-updated) --}}
                            <a href="{{ route('storefront.keranjang') }}"
                               x-data="{ count: {{ $cartCount }}, bump: false }"
                               @cart-updated.window="count = ($event.detail?.count ?? count); bump = true; setTimeout(() => bump = false, 300)"
                               class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                               aria-label="Keranjang">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('cart') }}" />
                                </svg>
                                <span x-show="count > 0" x-text="count"
                                      :class="bump ? 'scale-125' : 'scale-100'"
                                      @if ($cartCount == 0) style="display:none" @endif
                                      class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.125rem] items-center justify-center rounded-full bg-brand px-1 text-[0.625rem] font-bold leading-tight text-white transition-transform duration-200">{{ $cartCount ?: '' }}</span>
                            </a>

                            @if ($sekolah)
                                @include('partials.account-menu')
                            @else
                                <a href="{{ route('sekolah.masuk') }}" class="inline-flex min-h-[36px] items-center rounded-lg px-2.5 text-sm font-semibold text-white/80 transition-colors hover:text-white sm:px-3">Masuk</a>
                                <a href="{{ route('sekolah.daftar') }}" class="inline-flex min-h-[36px] items-center rounded-lg bg-brand px-3 text-sm font-bold text-white transition-colors hover:bg-brand-hover sm:px-3.5">Daftar</a>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="bg-navy-900">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 sm:px-6">
                    <div class="text-sm font-extrabold text-white">
                        Delapan Mata Air
                        <span class="ml-1 text-xs font-normal text-white/40">· Studio Foto</span>
                    </div>
                    <div class="text-xs text-white/50">&copy; {{ date('Y') }} DMA · Studio Foto Sekolah</div>
                </div>
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
