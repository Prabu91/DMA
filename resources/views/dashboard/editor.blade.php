<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard editor</h1>
        <p class="text-sm text-ink-muted">Antrian desain dan produksi.</p>
    </x-slot>

    <div class="space-y-6">
        @include('dashboard.partials.stats', ['stats' => $data['stats']])

        <x-card title="Antrian desain">
            <div class="py-8 text-center">
                <p class="text-sm text-ink-muted">Antrian desain belum tersedia.</p>
                <p class="mt-1 text-xs text-ink-muted">Modul proofing &amp; desain menyusul pada fase berikutnya.</p>
            </div>
        </x-card>
    </div>
</x-app-layout>
