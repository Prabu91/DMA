<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}Kanban DMA</title>

        <meta name="theme-color" content="#191B52">
        @include('partials.favicon')

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            [x-cloak]{display:none !important;}
            /* Saat diseret, kartu bayangan tampil miring seperti Trello. */
            .sortable-drag { transform: rotate(3deg); }
            .sortable-ghost { opacity: .35; }
            /* Deskripsi & komentar berformat (Markdown). */
            .isi-teks > *:first-child { margin-top: 0; }
            .isi-teks > *:last-child { margin-bottom: 0; }
            .isi-teks p, .isi-teks ul, .isi-teks ol, .isi-teks pre, .isi-teks blockquote { margin: .5rem 0; }
            .isi-teks h1, .isi-teks h2, .isi-teks h3 { font-weight: 600; margin: .75rem 0 .35rem; }
            .isi-teks h1 { font-size: 1.15rem; }
            .isi-teks h2 { font-size: 1.05rem; }
            .isi-teks h3 { font-size: 1rem; }
            .isi-teks ul { list-style: disc; padding-left: 1.25rem; }
            .isi-teks ol { list-style: decimal; padding-left: 1.4rem; }
            .isi-teks li { margin: .15rem 0; }
            .isi-teks a { color: #2E3192; text-decoration: underline; }
            .isi-teks code { background: #E9EBEE; border-radius: .25rem; padding: .05rem .3rem; font-size: .9em; }
            .isi-teks pre { background: #E9EBEE; border-radius: .5rem; padding: .6rem .75rem; overflow-x: auto; }
            .isi-teks pre code { background: none; padding: 0; }
            .isi-teks blockquote { border-left: 3px solid #DCDFE4; padding-left: .75rem; color: #7A7C86; }
            .isi-teks hr { border-color: #DCDFE4; margin: .75rem 0; }
            .isi-teks img { max-width: 100%; border-radius: .5rem; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="flex h-dvh flex-col bg-page">
            <header class="flex h-12 shrink-0 items-center justify-between gap-3 bg-navy-900 px-3 text-white sm:px-4">
                <div class="flex min-w-0 items-center gap-1 sm:gap-4">
                    {{-- Menu ringkas: di layar HP tautan navigasi disembunyikan, jadi dikumpulkan di sini. --}}
                    <div class="relative sm:hidden" x-data="{ buka: false }">
                        <button type="button" x-on:click="buka = ! buka" x-on:keydown.escape.window="buka = false"
                                :aria-expanded="buka" aria-label="Menu navigasi"
                                class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-white/10">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                        </button>
                        <div x-show="buka" x-cloak x-on:click.outside="buka = false"
                             class="absolute left-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-card py-1 text-sm text-ink shadow-lg">
                            <a href="{{ route('kanban.beranda') }}" wire:navigate class="block px-3 py-2.5 hover:bg-page">Semua board</a>
                            <a href="{{ route('kanban.kartu-saya') }}" wire:navigate class="block px-3 py-2.5 hover:bg-page">Kartu saya</a>
                            <form method="GET" action="{{ route('kanban.kartu-saya') }}" class="border-t border-line px-3 py-2">
                                <input type="hidden" name="tab" value="semua">
                                <label for="cari-hp" class="text-xs font-medium text-ink-muted">Cari kartu di semua board</label>
                                <input id="cari-hp" type="search" name="q" placeholder="Judul kartu…"
                                       class="mt-1 block min-h-[38px] w-full rounded-md border-line text-sm focus:border-brand focus:ring-brand/30">
                            </form>
                            <a href="{{ rtrim(config('app.url'), '/') }}/app/dashboard" class="block border-t border-line px-3 py-2.5 hover:bg-page">Panel staf ↗</a>
                        </div>
                    </div>

                    <a href="{{ route('kanban.beranda') }}" wire:navigate class="flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-white/10">
                        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-white text-[11px] font-semibold text-navy">DMA</span>
                        <span class="hidden text-sm font-semibold sm:inline">Kanban</span>
                    </a>
                    <a href="{{ route('kanban.beranda') }}" wire:navigate class="hidden rounded-md px-2 py-1.5 text-sm text-white/85 hover:bg-white/10 hover:text-white sm:block">Semua board</a>
                    <a href="{{ route('kanban.kartu-saya') }}" wire:navigate class="hidden rounded-md px-2 py-1.5 text-sm text-white/85 hover:bg-white/10 hover:text-white sm:block">Kartu saya</a>
                    <form method="GET" action="{{ route('kanban.kartu-saya') }}" class="hidden lg:block">
                        <input type="hidden" name="tab" value="semua">
                        <label for="cari-global" class="sr-only">Cari kartu di semua board</label>
                        <input id="cari-global" type="search" name="q" placeholder="Cari kartu…"
                               class="h-8 w-48 rounded-md border-0 bg-white/15 px-2.5 text-sm text-white placeholder:text-white/70 focus:bg-white focus:text-ink focus:placeholder:text-ink-muted focus:ring-2 focus:ring-brand">
                    </form>
                </div>

                <div class="flex items-center gap-1 sm:gap-2" x-data="{ buka: false }">
                    @php $saya = auth()->user(); @endphp
                    <a href="{{ rtrim(config('app.url'), '/') }}/app/dashboard"
                       class="hidden rounded-md px-2 py-1.5 text-sm text-white/85 hover:bg-white/10 hover:text-white md:block">Panel staf ↗</a>
                    <livewire:kanban.lonceng />
                    <div class="relative">
                        <button type="button" x-on:click="buka = ! buka" x-on:keydown.escape.window="buka = false"
                                aria-haspopup="menu" :aria-expanded="buka"
                                class="flex h-9 items-center gap-2 rounded-full pl-1 pr-2 hover:bg-white/10">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-semibold text-ink">
                                {{ mb_strtoupper(mb_substr($saya->nama ?? $saya->name, 0, 2)) }}
                            </span>
                            <span class="hidden max-w-[10rem] truncate text-sm sm:block">{{ $saya->nama ?? $saya->name }}</span>
                        </button>
                        <div x-show="buka" x-cloak x-transition.opacity x-on:click.outside="buka = false" role="menu"
                             class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-card py-1 text-sm text-ink shadow-lg">
                            <div class="border-b border-line px-3 py-2">
                                <div class="font-medium">{{ $saya->nama ?? $saya->name }}</div>
                                <div class="truncate text-xs text-ink-muted">{{ $saya->email }}</div>
                            </div>
                            <a href="{{ rtrim(config('app.url'), '/') }}/app/dashboard" role="menuitem" class="block px-3 py-2 hover:bg-page">Panel staf ↗</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="block w-full px-3 py-2 text-left hover:bg-page">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-h-0 flex-1">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
