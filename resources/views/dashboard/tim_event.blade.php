@include('dashboard.partials.shell', [
    'title' => 'Dashboard Tim Event',
    'subtitle' => 'Jadwal dan penugasan event Anda.',
    'cards' => [
        ['label' => 'Event Hari Ini'],
        ['label' => 'Order Ditugaskan'],
        ['label' => 'Event Minggu Ini'],
    ],
])
