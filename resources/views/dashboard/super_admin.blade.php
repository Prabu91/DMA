@include('dashboard.partials.shell', [
    'title' => 'Dashboard Super Admin',
    'subtitle' => 'Akses penuh seluruh cabang dan konfigurasi sistem.',
    'cards' => [
        ['label' => 'Total Cabang'],
        ['label' => 'Total Order'],
        ['label' => 'Total Sekolah'],
        ['label' => 'Total Pengguna'],
    ],
])
