<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title>{{ config('app.name', 'DMA') }} — Sistem operasional & booking</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500&display=swap" rel="stylesheet" />

        <!-- PWA -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="theme-color" content="#E08020">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="relative flex min-h-screen flex-col overflow-hidden bg-page">
            {{-- Sentuhan hangat: panel brand-tint lembut, flat tanpa gradient berat. --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-brand/5"></div>
            <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand/10 blur-3xl"></div>

            <div class="relative mx-auto flex w-full max-w-6xl flex-1 flex-col px-5 sm:px-8">
                {{-- Top bar --}}
                <header class="flex items-center justify-between py-6">
                    <x-brand-logo size="md" />
                    <div class="flex items-center gap-3">
                        <span class="hidden rounded-md bg-navy/10 px-2 py-0.5 text-xs font-medium text-navy sm:inline">
                            Alat kerja internal
                        </span>
                        @auth
                            <x-button href="{{ route('app.dashboard') }}" variant="ghost" size="sm">Dashboard</x-button>
                        @else
                            <x-button href="{{ route('login') }}" variant="ghost" size="sm">Masuk</x-button>
                        @endauth
                    </div>
                </header>

                {{-- Hero: 1 kolom (mobile) → 2 kolom (desktop) --}}
                <main class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-2 lg:gap-16 lg:py-16">
                    {{-- Kiri: pesan + aksi --}}
                    <div class="max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand/10 px-3 py-1 text-xs font-medium text-brand">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand"></span>
                            Studio foto DMA
                        </span>

                        <h1 class="mt-5 text-3xl font-medium leading-tight text-ink sm:text-4xl lg:text-5xl">
                            Sistem operasional &amp; booking studio foto
                        </h1>
                        <p class="mt-4 text-sm leading-relaxed text-ink-muted sm:text-base">
                            Kelola booking sekolah, jadwal event, dan produksi lintas cabang dalam satu tempat yang rapi dan cepat.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <x-button href="{{ route('app.dashboard') }}" size="lg" class="w-full sm:w-auto">
                                    Buka dashboard
                                </x-button>
                            @else
                                <x-button href="{{ route('login') }}" size="lg" class="w-full sm:w-auto">
                                    Masuk
                                </x-button>
                                @if (Route::has('register'))
                                    <x-button href="{{ route('register') }}" variant="secondary" size="lg" class="w-full sm:w-auto">
                                        Daftar
                                    </x-button>
                                @endif
                            @endauth
                        </div>

                        <ul class="mt-10 grid gap-3 sm:grid-cols-3 lg:block lg:space-y-3">
                            @foreach ([
                                ['Multi-cabang', 'Data tiap cabang terisolasi otomatis.'],
                                ['Peran jelas', 'Akses menyesuaikan role tim.'],
                                ['Siap di ponsel', 'Bisa dipasang seperti aplikasi.'],
                            ] as [$judul, $isi])
                                <li class="flex gap-3">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-status-success/10">
                                        <svg class="h-3 w-3 text-status-success" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </span>
                                    <span class="text-sm text-ink">
                                        <span class="font-medium">{{ $judul }}.</span>
                                        <span class="text-ink-muted">{{ $isi }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Kanan: panel preview produk (tampil di bawah pada mobile) --}}
                    <div class="lg:pl-4">
                        <div class="rounded-xl border border-line bg-card p-5 shadow-sm sm:p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar name="DMA Bandung" size="sm" />
                                    <div class="leading-tight">
                                        <div class="text-sm font-medium text-ink">DMA Bandung</div>
                                        <div class="text-xs text-ink-muted">Ringkasan hari ini</div>
                                    </div>
                                </div>
                                <span class="rounded-md bg-navy/10 px-2 py-0.5 text-xs font-medium text-navy">Cabang</span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <x-stat-card label="Booking aktif" value="24" hint="4 baru" accent="brand" />
                                <x-stat-card label="Menunggu DP" value="7" accent="pending" />
                            </div>

                            <div class="mt-5 space-y-px overflow-hidden rounded-lg border border-line">
                                @foreach ([
                                    ['SDN 1 Merdeka', 'Foto kelas', 'success', 'Lunas'],
                                    ['SD Harapan', 'Album', 'pending', 'Menunggu DP'],
                                    ['SMP Nusantara', 'Cetak & frame', 'info', 'Proses'],
                                ] as [$sekolah, $item, $variant, $label])
                                    <div class="flex items-center justify-between gap-3 border-b border-line bg-card px-3 py-2.5 last:border-b-0">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm text-ink">{{ $sekolah }}</div>
                                            <div class="truncate text-xs text-ink-muted">{{ $item }}</div>
                                        </div>
                                        <x-badge :variant="$variant">{{ $label }}</x-badge>
                                    </div>
                                @endforeach
                            </div>

                            <p class="mt-4 text-center text-xs text-ink-muted">Ilustrasi tampilan — data contoh.</p>
                        </div>
                    </div>
                </main>

                {{-- Footer --}}
                <footer class="border-t border-line py-6 text-xs text-ink-muted">
                    &copy; {{ date('Y') }} DMA Studio Foto — alat kerja internal.
                </footer>
            </div>
        </div>
    </body>
</html>
