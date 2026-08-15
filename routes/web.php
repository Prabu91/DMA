<?php

use App\Http\Controllers\CabangController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\Sekolah\AuthController as SekolahAuthController;
use App\Http\Controllers\Sekolah\EmailVerificationNotificationController as SekolahVerifyNotifyController;
use App\Http\Controllers\Sekolah\EmailVerificationPromptController as SekolahVerifyPromptController;
use App\Http\Controllers\Sekolah\PasswordController as SekolahPasswordController;
use App\Http\Controllers\Sekolah\ProfileController as SekolahProfileController;
use App\Http\Controllers\Sekolah\RegisterController as SekolahRegisterController;
use App\Http\Controllers\Sekolah\VerifyEmailController as SekolahVerifyEmailController;
use App\Livewire\Booking\KotakMasuk;
use App\Livewire\Katalog\AturanFreeIndex;
use App\Livewire\Katalog\DesainIndex;
use App\Livewire\Katalog\KategoriIndex;
use App\Livewire\Katalog\PaketIndex;
use App\Livewire\Katalog\ProdukForm;
use App\Livewire\Katalog\ProdukIndex;
use App\Livewire\Sekolah\SekolahIndex;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| STOREFRONT (publik + guard 'sekolah') — di "/"
|--------------------------------------------------------------------------
| Home = katalog landing (browse terbuka tanpa login). Staf (web) → panel.
*/
Route::get('/', [StorefrontController::class, 'home'])->name('storefront.home');

// Katalog publik — browse & detail TANPA auth (reuse Etalase/EtalaseDetail konteks 'publik').
Route::view('/katalog', 'storefront.katalog-index')->name('storefront.katalog.index');
Route::get('/katalog/{tipe}/{id}', fn (string $tipe, string $id) => view('storefront.katalog-detail', ['tipe' => $tipe, 'id' => (int) $id]))
    ->whereIn('tipe', ['produk', 'paket'])
    ->whereNumber('id')
    ->name('storefront.katalog.detail');

// Keranjang guest (session) — tanpa auth.
Route::view('/keranjang', 'storefront.keranjang')->name('storefront.keranjang');

// Checkout — login-gate di controller (wajib login sekolah + verified + cabang != null).
Route::get('/checkout', [CheckoutController::class, 'show'])->name('storefront.checkout');

/*
|--------------------------------------------------------------------------
| PANEL STAF — semua di "/app" (guard web + spatie; CabangScope via model)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('app.')->middleware(['auth', 'verified'])->group(function () {
    // Titik masuk: arahkan ke dashboard sesuai role.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    foreach (DashboardController::ROLE_DASHBOARDS as $role) {
        Route::get('/dashboard/'.str_replace('_', '-', $role), [DashboardController::class, 'show'])
            ->middleware('role:'.$role)
            ->defaults('role', $role)
            ->name('dashboard.'.$role);
    }

    // Profil.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Master data — khusus super_admin.
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('cabang', CabangController::class)->parameters(['cabang' => 'cabang'])->except('show');
        Route::resource('pengguna', PenggunaController::class)->parameters(['pengguna' => 'pengguna'])->except('show');
        Route::get('/kecamatan', \App\Livewire\Katalog\KecamatanIndex::class)->name('kecamatan.index');
        Route::get('/report-order', \App\Livewire\ReportOrder::class)->name('report-order');
    });

    // Katalog global — super_admin & operasional.
    Route::middleware('role:super_admin|operasional')->group(function () {
        Route::get('/katalog/kategori', KategoriIndex::class)->name('kategori.index');
        Route::get('/katalog/produk', ProdukIndex::class)->name('produk.index');
        Route::get('/katalog/produk/create', ProdukForm::class)->name('produk.create');
        Route::get('/katalog/produk/{produk}/edit', ProdukForm::class)->name('produk.edit');
        Route::get('/katalog/paket', PaketIndex::class)->name('paket.index');
        Route::get('/katalog/desain', DesainIndex::class)->name('desain.index');
        Route::get('/katalog/aturan-free', AturanFreeIndex::class)->name('aturan-free.index');
    });

    // Etalase + booking staf + kotak masuk + CRUD sekolah — peran booking.
    Route::middleware('role:super_admin|operasional|admin_sales|marketing')->group(function () {
        Route::view('/etalase', 'etalase.index')->name('etalase.index');
        Route::get('/etalase/{tipe}/{id}', fn (string $tipe, string $id) => view('etalase.detail', ['tipe' => $tipe, 'id' => (int) $id]))
            ->whereIn('tipe', ['produk', 'paket'])
            ->whereNumber('id')
            ->name('etalase.detail');
        Route::view('/keranjang', 'booking.keranjang-staf')->name('keranjang');
        Route::view('/review', 'booking.review-staf')->name('review');
        Route::get('/order', \App\Livewire\Booking\OrderIndex::class)->name('order.index');
        Route::get('/order/{id}', fn (string $id) => view('booking.order-staf', ['orderId' => (int) $id]))
            ->whereNumber('id')->name('order.show');
        Route::get('/order/{id}/pdf', [OrderPdfController::class, 'staf'])->whereNumber('id')->name('order.pdf');
        Route::get('/order/{id}/ste', [OrderPdfController::class, 'ste'])->whereNumber('id')->name('order.ste');
        Route::get('/kotak-masuk', KotakMasuk::class)->name('kotak-masuk');
        Route::get('/sekolah', SekolahIndex::class)->name('sekolah.index');
    });

    // Aktivitas (audit lintas order) — admin & area (area ter-scope cabang).
    Route::middleware('role:super_admin|operasional|admin_sales')->group(function () {
        Route::get('/aktivitas', \App\Livewire\ActivityIndex::class)->name('aktivitas');
    });

    // Finance — super_admin + admin_sales (admin area = admin sales = admin finance).
    Route::middleware('role:super_admin|admin_sales')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/sales', \App\Livewire\Finance\AllDataSales::class)->name('sales');
        Route::get('/penagihan', \App\Livewire\Finance\PenagihanHarian::class)->name('penagihan');
        Route::get('/event-harian', \App\Livewire\Finance\TransaksiEventHarian::class)->name('event-harian');
    });

    // Event tim event — jadwal, detail pelaksanaan, STE. Tim event + admin oversight.
    Route::middleware('role:super_admin|operasional|tim_event')->group(function () {
        Route::get('/event', \App\Livewire\Event\EventIndex::class)->name('event.index');
        Route::get('/event/{orderId}', \App\Livewire\Event\EventDetail::class)->whereNumber('orderId')->name('event.show');
        Route::get('/event/{id}/ste', [OrderPdfController::class, 'ste'])->whereNumber('id')->name('event.ste');
    });
});

/*
|--------------------------------------------------------------------------
| AUTH SEKOLAH (storefront) — login EMAIL + registrasi mandiri
|--------------------------------------------------------------------------
| id_sekolah = kode akun (bukan kredensial). Guard 'sekolah'.
*/
Route::middleware('guest:sekolah')->group(function () {
    Route::get('/masuk', [SekolahAuthController::class, 'create'])->name('sekolah.masuk');
    Route::post('/masuk', [SekolahAuthController::class, 'store'])->name('sekolah.masuk.store');
    Route::get('/daftar', [SekolahRegisterController::class, 'create'])->name('sekolah.daftar');
    Route::post('/daftar', [SekolahRegisterController::class, 'store'])->name('sekolah.daftar.store');
});

// Verifikasi email sekolah — prefix /verifikasi/* (hindari bentrok route Breeze).
// Tautan verify cukup bertanda-tangan (signed); tak butuh sesi login.
Route::get('/verifikasi/{id}/{hash}', SekolahVerifyEmailController::class)
    ->middleware('signed')->name('sekolah.verification.verify');

Route::middleware('auth:sekolah')->group(function () {
    Route::post('/keluar', [SekolahAuthController::class, 'destroy'])->name('sekolah.logout');
    Route::get('/verifikasi', SekolahVerifyPromptController::class)->name('sekolah.verification.notice');
    Route::post('/verifikasi/kirim', [SekolahVerifyNotifyController::class, 'store'])
        ->middleware('throttle:6,1')->name('sekolah.verification.send');
});

/*
|--------------------------------------------------------------------------
| Portal sekolah (guard 'sekolah') — "/sekolah/*" (butuh email terverifikasi)
|--------------------------------------------------------------------------
*/
Route::prefix('sekolah')->name('sekolah.')->middleware('auth:sekolah')->group(function () {
    // Profil & ganti kata sandi tetap boleh walau belum verifikasi.
    Route::get('profil', [SekolahProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profil', [SekolahProfileController::class, 'update'])->name('profile.update');
    Route::get('password', [SekolahPasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [SekolahPasswordController::class, 'update'])->name('password.update');

    // Konten portal. Verifikasi email TIDAK diwajibkan (sementara; nanti via WA).
    Route::view('katalog', 'sekolah.katalog-index')->name('katalog.index');
    Route::get('katalog/{tipe}/{id}', fn (string $tipe, string $id) => view('sekolah.katalog-detail', ['tipe' => $tipe, 'id' => (int) $id]))
        ->whereIn('tipe', ['produk', 'paket'])
        ->whereNumber('id')
        ->name('katalog.detail');
    Route::view('keranjang', 'booking.keranjang-sekolah')->name('keranjang');
    Route::view('review', 'booking.review-sekolah')->name('review');
    Route::view('riwayat', 'booking.riwayat-sekolah')->name('riwayat.index');
    Route::get('riwayat/{id}', fn (string $id) => view('booking.order-sekolah', ['orderId' => (int) $id]))
        ->whereNumber('id')->name('riwayat.show');
    Route::get('riwayat/{id}/pdf', [OrderPdfController::class, 'sekolah'])->whereNumber('id')->name('riwayat.pdf');
});

// Auth staf bawaan Breeze — tetap di root (login, register, password, verifikasi).
require __DIR__.'/auth.php';
