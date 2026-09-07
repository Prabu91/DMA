<?php

namespace App\Http\Middleware;

use App\Support\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API report: token dikirim lewat header Authorization: Bearer <token>.
 *
 * Token disimpan sebagai hash, jadi kebocoran isi tabel pengaturan tidak
 * langsung memberi akses. Bila token belum pernah dibuat, API tertutup —
 * bukan terbuka.
 */
class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = Pengaturan::teks(Pengaturan::API_TOKEN_HASH);

        if (! $hash) {
            return response()->json([
                'message' => 'API report belum diaktifkan. Buat token di halaman Pengaturan.',
            ], 503);
        }

        $token = $request->bearerToken();

        if (! $token || ! Hash::check($token, $hash)) {
            return response()->json(['message' => 'Token tidak sah.'], 401);
        }

        return $next($request);
    }
}
