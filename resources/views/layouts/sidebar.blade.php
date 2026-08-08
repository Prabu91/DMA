@php $menu = \App\Support\RoleMenu::for(auth()->user()); @endphp

<aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-line bg-card lg:flex">
    <div class="flex h-16 items-center border-b border-line px-5">
        <a href="{{ route('app.dashboard') }}">
            <x-brand-logo size="md" />
        </a>
    </div>

    <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
        @php $grupSekarang = '__none__'; @endphp
        @foreach ($menu as $item)
            @if (($item['group'] ?? null) !== $grupSekarang)
                @php $grupSekarang = $item['group'] ?? null; @endphp
                @if ($grupSekarang)
                    <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-ink-muted/60">{{ $grupSekarang }}</div>
                @endif
            @endif

            @php $active = $item['route'] && request()->routeIs($item['active']); @endphp
            @if ($item['route'])
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                       'bg-brand/10 text-brand' => $active,
                       'text-ink-muted hover:bg-page hover:text-ink' => ! $active,
                   ])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    <span>{{ $item['label'] }}</span>
                </a>
            @else
                <span class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-muted/50"
                      title="Segera hadir">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    <span>{{ $item['label'] }}</span>
                    <span class="ml-auto rounded bg-ink/5 px-1.5 py-0.5 text-[10px]">Segera</span>
                </span>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-line p-4 text-xs text-ink-muted">
        {{ config('app.name', 'DMA') }} · v1
    </div>
</aside>
