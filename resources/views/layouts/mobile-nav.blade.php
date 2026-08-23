@php $menu = \App\Support\RoleMenu::for(auth()->user()); @endphp

{{-- Navigasi mobile: drawer geser dari kiri berisi MENU LENGKAP (dengan grup).
     Menggantikan bottom-nav lama yang memangkas menu. Butuh scope Alpine
     `sidebarOpen` dari layouts.app. Hanya aktif < lg. --}}
<div class="lg:hidden" x-cloak>
    {{-- Backdrop --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-on:click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-sm"
         aria-hidden="true"></div>

    {{-- Panel drawer --}}
    <aside x-show="sidebarOpen"
           x-transition:enter="transition-transform ease-out duration-200"
           x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition-transform ease-in duration-150"
           x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           x-on:keydown.escape.window="sidebarOpen = false"
           class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[82%] flex-col border-r border-line bg-card"
           aria-label="Navigasi">
        <div class="flex h-16 items-center justify-between border-b border-line px-5">
            <a href="{{ route('app.dashboard') }}" x-on:click="sidebarOpen = false">
                <x-brand-logo size="md" />
            </a>
            <button type="button" x-on:click="sidebarOpen = false"
                    class="-mr-1.5 rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-page hover:text-ink"
                    aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
            @include('layouts.partials.nav-items', ['menu' => $menu])
        </nav>

        <div class="border-t border-line p-4 text-xs text-ink-muted">
            {{ config('app.name', 'DMA') }} · v1
        </div>
    </aside>
</div>
