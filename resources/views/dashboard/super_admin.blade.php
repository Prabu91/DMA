<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard super admin</h1>
        <p class="text-sm text-ink-muted">Ringkasan seluruh cabang dan akses master data.</p>
    </x-slot>

    @include('dashboard.partials.lintas-cabang', ['data' => $data])
</x-app-layout>
