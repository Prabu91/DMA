<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber tunggal item navigasi per role.
 *
 * Setiap item: label, icon (path 'd' heroicon), route (nama route atau null),
 * dan active (pola route untuk state aktif). route null = fitur Fase 2
 * (ditampilkan sebagai placeholder "Segera").
 */
class RoleMenu
{
    /**
     * Path SVG (atribut d) heroicons outline yang dipakai menu.
     */
    private const ICONS = [
        'home' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.5a.75.75 0 00.75.75h4.5a.75.75 0 00.75-.75V15a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v5.25a.75.75 0 00.75.75h4.5a.75.75 0 00.75-.75V9.75M8.25 21h8.25',
        'building' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
        'school' => 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
        'product' => 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        'order' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
        'users' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        'photo' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        'tag' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z',
        'cube' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'gift' => 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
        'store' => 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 12.75h3.75a.75.75 0 00.75-.75V9.75a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75V12c0 .414.336.75.75.75z',
        'inbox' => 'M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z',
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    ];

    /**
     * Definisi item tambahan (selain Dashboard) per role, DIKELOMPOKKAN per
     * fungsi. Format: [role => [grup => [ [label, icon-key, route?, active?] ]]].
     * route null = fitur belum ada (placeholder "Segera"). Urutan grup =
     * urutan tampil di sidebar.
     */
    private const MENUS = [
        'super_admin' => [
            'Operasional' => [
                ['Kotak masuk', 'inbox', 'kotak-masuk', 'kotak-masuk'],
                ['Order', 'order', 'order.index', 'order.*'],
                ['Aktivitas', 'clock', 'aktivitas', 'aktivitas'],
            ],
            'Katalog' => [
                ['Kategori', 'tag', 'kategori.index', 'kategori.*'],
                ['Produk', 'product', 'produk.index', 'produk.*'],
                ['Paket', 'cube', 'paket.index', 'paket.*'],
                ['Desain', 'photo', 'desain.index', 'desain.*'],
                ['Free sekolah', 'gift', 'aturan-free.index', 'aturan-free.*'],
                ['Katalog', 'store', 'etalase.index', 'etalase.*'],
            ],
            'Data master' => [
                ['Cabang', 'building', 'cabang.index', 'cabang.*'],
                ['Pengguna', 'users', 'pengguna.index', 'pengguna.*'],
                ['Sekolah', 'school', 'sekolah.index', 'sekolah.*'],
            ],
        ],
        'operasional' => [
            'Operasional' => [
                ['Kotak masuk', 'inbox', 'kotak-masuk', 'kotak-masuk'],
                ['Order', 'order', 'order.index', 'order.*'],
                ['Aktivitas', 'clock', 'aktivitas', 'aktivitas'],
            ],
            'Katalog' => [
                ['Kategori', 'tag', 'kategori.index', 'kategori.*'],
                ['Produk', 'product', 'produk.index', 'produk.*'],
                ['Paket', 'cube', 'paket.index', 'paket.*'],
                ['Desain', 'photo', 'desain.index', 'desain.*'],
                ['Free sekolah', 'gift', 'aturan-free.index', 'aturan-free.*'],
                ['Katalog', 'store', 'etalase.index', 'etalase.*'],
            ],
            'Data master' => [
                ['Sekolah', 'school', 'sekolah.index', 'sekolah.*'],
            ],
        ],
        'area' => [
            'Operasional' => [
                ['Kotak masuk', 'inbox', 'kotak-masuk', 'kotak-masuk'],
                ['Order', 'order', 'order.index', 'order.*'],
                ['Aktivitas', 'clock', 'aktivitas', 'aktivitas'],
            ],
            'Katalog' => [
                ['Katalog', 'store', 'etalase.index', 'etalase.*'],
            ],
            'Data master' => [
                ['Sekolah', 'school', 'sekolah.index', 'sekolah.*'],
            ],
        ],
        'marketing' => [
            'Operasional' => [
                ['Kotak masuk', 'inbox', 'kotak-masuk', 'kotak-masuk'],
                ['Order', 'order', 'order.index', 'order.*'],
            ],
            'Katalog' => [
                ['Katalog', 'store', 'etalase.index', 'etalase.*'],
            ],
            'Data master' => [
                ['Sekolah', 'school', 'sekolah.index', 'sekolah.*'],
            ],
        ],
        'tim_event' => [
            'Operasional' => [
                ['Jadwal Event', 'calendar', 'event.index', 'event.*'],
            ],
        ],
        'editor' => [
            'Katalog' => [
                ['Desain', 'photo'],
            ],
        ],
    ];

    /**
     * Bangun daftar item menu untuk user (FLAT, tiap item punya key `group`).
     * Dashboard selalu paling atas dengan group null (berdiri sendiri).
     *
     * @return array<int, array{label:string, icon:string, route:?string, active:string, group:?string}>
     */
    public static function for(?User $user): array
    {
        $role = $user?->getRoleNames()->first();

        // Semua halaman staf berada di area panel "/app" (nama route ber-prefix "app.").
        $dashboardRoute = ($role && array_key_exists($role, self::MENUS))
            ? 'dashboard.'.$role
            : 'dashboard';

        $items = [[
            'label' => 'Dashboard',
            'icon' => self::ICONS['home'],
            'route' => 'app.'.$dashboardRoute,
            'active' => 'app.dashboard*',
            'group' => null,
        ]];

        foreach (self::MENUS[$role] ?? [] as $grup => $entries) {
            foreach ($entries as $entry) {
                [$label, $iconKey] = $entry;
                $route = $entry[2] ?? null;   // null = placeholder "Segera"
                $active = $entry[3] ?? '';

                $items[] = [
                    'label' => $label,
                    'icon' => self::ICONS[$iconKey],
                    'route' => $route ? 'app.'.$route : null,
                    'active' => $active !== '' ? 'app.'.$active : '',
                    'group' => $grup,
                ];
            }
        }

        return $items;
    }
}
