<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Dashboard marketing</h1>
        <p class="text-sm text-ink-muted">Sekolah dan order yang Anda kelola.</p>
    </x-slot>

    <div class="space-y-6">
        @include('dashboard.partials.stats', ['stats' => $data['stats']])

        @include('dashboard.partials.order-list', [
            'orders' => $data['recentOrders'],
            'title' => 'Order terbaru',
            'emptyText' => 'Belum ada order. Order yang Anda kelola akan muncul di sini.',
            'showAll' => true,
        ])
    </div>
</x-app-layout>
