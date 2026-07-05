<?php

use App\Http\Controllers\CabangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sekolah\AuthController as SekolahAuthController;
use App\Http\Controllers\Sekolah\PasswordController as SekolahPasswordController;
use App\Livewire\Booking\KotakMasuk;
use App\Livewire\Katalog\AturanFreeIndex;
use App\Livewire\Katalog\DesainIndex;
use App\Livewire\Katalog\KategoriIndex;
use App\Livewire\Katalog\PaketIndex;
use App\Livewire\Katalog\ProdukForm;
use App\Livewire\Katalog\ProdukIndex;
use App\Livewire\Sekolah\SekolahIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Titik masuk: mengarahkan user ke dashboard sesuai role.
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Dashboard per role, dilindungi middleware role milik spatie (RBAC).
foreach (DashboardController::ROLE_DASHBOARDS as $role) {
    Route::get('/dashboard/'.str_replace('_', '-', $role), [DashboardController::class, 'show'])
        ->middleware(['auth', 'verified', 'role:'.$role])
        ->defaults('role', $role)
        ->name('dashboard.'.$role);
}

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Aksi tim event: tandai event selesai.
    Route::post('/events/{order}/selesai', [EventController::class, 'complete'])->name('events.complete');
});

// Master data admin — khusus super_admin.
Route::middleware(['auth', 'verified', 'role:super_admin'])->group(function () {
    Route::resource('cabang', CabangController::class)
        ->parameters(['cabang' => 'cabang'])
        ->except('show');
    Route::resource('pengguna', PenggunaController::class)
        ->parameters(['pengguna' => 'pengguna'])
        ->except('show');
});

// Katalog global — super_admin & operasional.
Route::middleware(['auth', 'verified', 'role:super_admin|operasional'])->group(function () {
    Route::get('/katalog/kategori', KategoriIndex::class)->name('kategori.index');

    Route::get('/katalog/produk', ProdukIndex::class)->name('produk.index');
    Route::get('/katalog/produk/create', ProdukForm::class)->name('produk.create');
    Route::get('/katalog/produk/{produk}/edit', ProdukForm::class)->name('produk.edit');

    Route::get('/katalog/paket', PaketIndex::class)->name('paket.index');
    Route::get('/katalog/desain', DesainIndex::class)->name('desain.index');
    Route::get('/katalog/aturan-free', AturanFreeIndex::class)->name('aturan-free.index');
});

// Etalase katalog untuk staf yang melakukan booking (jalur marketing).
Route::middleware(['auth', 'verified', 'role:super_admin|operasional|area|marketing'])->group(function () {
    Route::view('/etalase', 'etalase.index')->name('etalase.index');
    Route::get('/etalase/{tipe}/{id}', fn (string $tipe, string $id) => view('etalase.detail', ['tipe' => $tipe, 'id' => (int) $id]))
        ->whereIn('tipe', ['produk', 'paket'])
        ->whereNumber('id')
        ->name('etalase.detail');
    Route::view('/keranjang', 'booking.keranjang-staf')->name('keranjang');
    Route::view('/review', 'booking.review-staf')->name('review');
    Route::get('/order/{id}', fn (string $id) => view('booking.order-staf', ['orderId' => (int) $id]))
        ->whereNumber('id')->name('order.show');
    Route::get('/order/{id}/pdf', [OrderPdfController::class, 'staf'])->whereNumber('id')->name('order.pdf');
    Route::get('/kotak-masuk', KotakMasuk::class)->name('kotak-masuk');
});

// Portal sekolah (guard terpisah 'sekolah') — /sekolah/*.
Route::prefix('sekolah')->name('sekolah.')->group(function () {
    Route::middleware('guest:sekolah')->group(function () {
        Route::get('login', [SekolahAuthController::class, 'create'])->name('login');
        Route::post('login', [SekolahAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:sekolah')->group(function () {
        Route::post('logout', [SekolahAuthController::class, 'destroy'])->name('logout');
        Route::view('beranda', 'sekolah.beranda')->name('beranda');
        Route::get('password', [SekolahPasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [SekolahPasswordController::class, 'update'])->name('password.update');

        // Etalase katalog (jalur sekolah self-service).
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
});

// Sekolah (per-cabang) — super_admin, operasional, area, marketing.
Route::middleware(['auth', 'verified', 'role:super_admin|operasional|area|marketing'])->group(function () {
    Route::get('/sekolah', SekolahIndex::class)->name('sekolah.index');
});

require __DIR__.'/auth.php';
