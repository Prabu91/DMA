<?php

use App\Http\Controllers\Api\ReportOrderController;
use Illuminate\Support\Facades\Route;

/*
 * API baca-saja untuk web report eksternal DMA.
 *
 * Autentikasi: header `Authorization: Bearer <token>`; token dibuat & dicabut
 * di halaman Pengaturan. Ditujukan untuk pemanggilan server-ke-server —
 * tidak ada setelan CORS, jadi tidak bisa dipanggil langsung dari peramban.
 */
// throttle: pengaman kalau loop polling di sisi pemanggil lepas kendali —
// 120 permintaan/menit sudah sangat longgar untuk polling tiap 30 detik.
Route::middleware(['api.token', 'throttle:120,1'])->prefix('v1')->name('api.')->group(function () {
    Route::get('/report-order', [ReportOrderController::class, 'index'])->name('report-order.index');
    Route::get('/report-order/ringkasan', [ReportOrderController::class, 'ringkasan'])->name('report-order.ringkasan');
});
