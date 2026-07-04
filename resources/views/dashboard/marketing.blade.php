@include('dashboard.partials.shell', [
    'title' => 'Dashboard Marketing',
    'subtitle' => 'Sekolah dan order yang Anda kelola.',
    'cards' => [
        ['label' => 'Sekolah Saya'],
        ['label' => 'Order Saya'],
        ['label' => 'Booking Bulan Ini'],
        ['label' => 'Target'],
    ],
])
