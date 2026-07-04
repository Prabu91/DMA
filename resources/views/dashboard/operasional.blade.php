@include('dashboard.partials.shell', [
    'title' => 'Dashboard Operasional',
    'subtitle' => 'Pemantauan operasional lintas cabang.',
    'cards' => [
        ['label' => 'Order Aktif'],
        ['label' => 'Sekolah'],
        ['label' => 'Produk'],
        ['label' => 'Event Mendatang'],
    ],
])
