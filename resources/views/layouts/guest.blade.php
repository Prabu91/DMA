<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DMA') }} · Studio Foto</title>

        @include('partials.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-page lg:grid lg:grid-cols-2">
            {{-- Panel brand (desktop) — navy untuk kedalaman, dipakai hemat. --}}
            <div class="relative hidden flex-col justify-between overflow-hidden bg-navy p-10 text-white lg:flex">
                <div class="pointer-events-none absolute -bottom-24 -left-16 h-72 w-72 rounded-full bg-brand/20 blur-3xl"></div>

                <a href="{{ url('/') }}" class="relative">
                    <x-brand-logo size="lg" tone="light" />
                </a>

                <div class="relative max-w-md">
                    <h2 class="text-3xl font-medium leading-tight text-white">
                        Operasional &amp; booking studio foto, tertata rapi.
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-white/70">
                        Satu tempat untuk booking sekolah, jadwal event, dan produksi — lintas cabang, dengan akses sesuai peran.
                    </p>
                </div>

                <p class="relative text-xs text-white/50">
                    &copy; {{ date('Y') }} DMA Studio Foto — alat kerja internal.
                </p>
            </div>

            {{-- Panel form --}}
            <div class="flex min-h-screen flex-col justify-center px-5 py-10 sm:px-8 lg:min-h-0">
                <div class="mx-auto w-full max-w-sm">
                    {{-- Logo untuk mobile (panel brand disembunyikan) --}}
                    <a href="{{ url('/') }}" class="mb-8 inline-flex lg:hidden">
                        <x-brand-logo size="md" />
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
