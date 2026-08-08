<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware spatie/laravel-permission untuk proteksi route berbasis role.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'verified.sekolah' => \App\Http\Middleware\EnsureSekolahEmailVerified::class,
        ]);

        // Tamu di area sekolah (portal /sekolah/*, verifikasi /email/*, /keluar)
        // diarahkan ke login sekolah; selain itu (mis. /app/*) ke login staf.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('sekolah/*', 'verifikasi', 'verifikasi/*', 'keluar')
            ? route('sekolah.masuk')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
