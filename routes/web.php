<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
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
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
