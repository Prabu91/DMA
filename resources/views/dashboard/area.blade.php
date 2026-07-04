@include('dashboard.partials.shell', [
    'title' => 'Dashboard Area',
    'subtitle' => 'Ringkasan operasional cabang Anda.',
    'cards' => [
        ['label' => 'Order Cabang'],
        ['label' => 'Sekolah Cabang'],
        ['label' => 'Event Mendatang'],
        ['label' => 'Tim Aktif'],
    ],
])
