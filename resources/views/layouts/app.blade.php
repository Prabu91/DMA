<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DMA') }}</title>

        <!-- PWA -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#E08020">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-page">
            @include('layouts.sidebar')

            <div class="lg:pl-64">
                @include('layouts.topbar')

                {{-- Judul halaman (opsional) --}}
                @isset($header)
                    <div class="border-b border-line bg-card px-4 py-4 sm:px-6">
                        <div class="mx-auto w-full max-w-6xl">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                {{-- Konten; pb-24 agar tak tertutup bottom-nav di mobile --}}
                <main class="mx-auto w-full max-w-6xl px-4 py-6 pb-24 sm:px-6 lg:pb-8">
                    @include('layouts.flash')
                    {{ $slot }}
                </main>
            </div>

            @include('layouts.bottom-nav')
        </div>

        @livewireScripts

        <!-- Registrasi service worker PWA -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function (e) {
                        console.warn('SW registration failed:', e);
                    });
                });
            }
        </script>
    </body>
</html>
