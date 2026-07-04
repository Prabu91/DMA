@php
    // Maksimal 5 item pertama agar muat di layar ponsel.
    $items = collect(\App\Support\RoleMenu::for(auth()->user()))->take(5);
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 sm:hidden"
     aria-label="Navigasi bawah">
    <div class="grid h-16" style="grid-template-columns: repeat({{ max($items->count(), 1) }}, minmax(0, 1fr));">
        @foreach ($items as $item)
            @php $active = $item['route'] && request()->routeIs($item['active']); @endphp
            @if ($item['route'])
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex flex-col items-center justify-center gap-1 text-xs',
                       'text-indigo-600' => $active,
                       'text-gray-500 hover:text-gray-700' => ! $active,
                   ])>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    <span class="leading-none">{{ $item['label'] }}</span>
                </a>
            @else
                <span class="flex flex-col items-center justify-center gap-1 text-xs text-gray-300 cursor-not-allowed"
                      title="Segera hadir">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    <span class="leading-none">{{ $item['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
</nav>
