<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sumber data wilayah (public API)
    |--------------------------------------------------------------------------
    |
    | Impor kecamatan riil dari API wilayah Indonesia publik (emsifa —
    | api-wilayah-indonesia, berbasis kode BPS). Dipakai oleh:
    |   php artisan kecamatan:import
    |
    | Endpoint districts: {base}/districts/{kode_regency}.json
    */

    'emsifa' => [
        'base' => env('WILAYAH_API_BASE', 'https://www.emsifa.com/api-wilayah-indonesia/api'),
        'timeout' => (int) env('WILAYAH_API_TIMEOUT', 20),
    ],

    /*
     | Peta kota DMA → daftar kode kabupaten/kota (regency, BPS) sumber kecamatan.
     | Satu kota DMA boleh menarik dari beberapa regency (mis. "Jakarta" = 5 kota
     | administrasi DKI). Sesuaikan bila cakupan cabang berubah.
     */
    'regencies' => [
        'Bandung' => ['3273'],                                // Kota Bandung
        'Cianjur' => ['3203'],                                // Kab. Cianjur
        'Bogor' => ['3271', '3201'],                          // Kota + Kab. Bogor
        'Jakarta' => ['3171', '3172', '3173', '3174', '3175'], // 5 kota administrasi DKI
        'Tangerang' => ['3671', '3603', '3674'],              // Kota, Kab, Tangerang Selatan
        'Depok' => ['3276'],                                  // Kota Depok
        'Cikarang' => ['3216'],                               // Kab. Bekasi (Cikarang)
    ],

];
