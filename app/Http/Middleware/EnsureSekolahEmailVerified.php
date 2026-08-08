<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan sekolah (guard 'sekolah') sudah verifikasi email sebelum
 * mengakses konten portal. Yang belum → diarahkan ke halaman verifikasi.
 */
class EnsureSekolahEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $sekolah = $request->user('sekolah');

        if ($sekolah && ! $sekolah->hasVerifiedEmail()) {
            return redirect()->route('sekolah.verification.notice');
        }

        return $next($request);
    }
}
