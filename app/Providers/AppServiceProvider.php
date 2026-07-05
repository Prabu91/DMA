<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Middleware guest: sekolah yang sudah login diarahkan ke beranda sekolah,
        // staf ke dashboard (hindari bounce lintas-guard).
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => $request->is('sekolah/*')
                ? route('sekolah.beranda')
                : route('dashboard')
        );
    }
}
