<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard operasional</h1>
        <p class="text-sm text-ink-muted">Pemantauan operasional lintas cabang.</p>
    </x-slot>

    @include('dashboard.partials.lintas-cabang', ['data' => $data])
</x-app-layout>
