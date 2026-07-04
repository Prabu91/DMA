{{--
    Shell dashboard placeholder.
    Variabel: $title, $subtitle, $cards (array of ['label','value'?,'hint'?]).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-gray-700">{{ $subtitle }}</p>
                <p class="mt-1 text-sm text-gray-400">Halaman placeholder — konten fitur menyusul pada fase berikutnya.</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($cards as $card)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                        <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-800">{{ $card['value'] ?? '—' }}</div>
                        @isset($card['hint'])
                            <div class="mt-1 text-xs text-gray-400">{{ $card['hint'] }}</div>
                        @endisset
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
