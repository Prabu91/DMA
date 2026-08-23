<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DMA') }} · Studio Foto</title>

        <!-- PWA -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#191B52">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        @include('partials.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak]{display:none !important;}</style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-page" x-data="{ sidebarOpen: false }">
            @include('layouts.sidebar')
            @include('layouts.mobile-nav')

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

                <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
                    @include('layouts.flash')
                    {{ $slot }}
                </main>
            </div>
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
