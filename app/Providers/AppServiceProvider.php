<?php

namespace App\Providers;

use App\Models\Sekolah;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tamu yang sudah login diarahkan sesuai guard-nya (hindari bounce lintas-area).
        RedirectIfAuthenticated::redirectUsing(function () {
            if (auth('sekolah')->check()) {
                return route('storefront.katalog.index');
            }

            return route('app.dashboard');
        });

        // URL verifikasi email untuk sekolah → route storefront (bukan route staf).
        // (Hanya Sekolah yang MustVerifyEmail; blok else jaga-jaga.)
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $routeName = $notifiable instanceof Sekolah
                ? 'sekolah.verification.verify'
                : 'verification.verify';

            return URL::temporarySignedRoute($routeName, now()->addMinutes(60), [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);
        });
    }
}
