<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain khusus kanban
    |--------------------------------------------------------------------------
    |
    | Host yang hanya melayani kanban: membukanya langsung ke login staf, lalu
    | ke daftar board. Halaman lain di host ini dialihkan ke domain utama.
    | Kosongkan untuk mematikan (kanban tetap bisa dibuka di /kanban).
    |
    */
    'domain' => env('KANBAN_DOMAIN', 'app.8mataair.com'),

    /** Batas ukuran lampiran kartu (KB). */
    'maks_lampiran_kb' => (int) env('KANBAN_MAKS_LAMPIRAN_KB', 10240),
];
