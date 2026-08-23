@php $menu = \App\Support\RoleMenu::for(auth()->user()); @endphp

<aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-line bg-card lg:flex">
    <div class="flex h-16 items-center border-b border-line px-5">
        <a href="{{ route('app.dashboard') }}">
            <x-brand-logo size="md" />
        </a>
    </div>

    <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
        @include('layouts.partials.nav-items', ['menu' => $menu])
    </nav>

    <div class="border-t border-line p-4 text-xs text-ink-muted">
        {{ config('app.name', 'DMA') }} · v1
    </div>
</aside>
