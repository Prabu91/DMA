<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subdomain kanban (mis. app.8mataair.com) hanya melayani kanban:
 * - "/" langsung ke kanban (tamu otomatis diarahkan ke login staf);
 * - login, logout, reset kata sandi, dan aset tetap dilayani di sini;
 * - halaman lain (panel staf, storefront) dialihkan ke domain utama.
 */
class DomainKanban
{
    /** Path yang boleh dilayani di subdomain kanban. */
    private const BOLEH = [
        'kanban', 'kanban/*',
        'login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*', 'confirm-password',
        'livewire*', 'build/*', 'storage/*', 'up',
        'favicon*', 'manifest.json', 'sw.js', 'offline.html', 'icons/*', 'images/*',
    ];

    public static function aktif(Request $request): bool
    {
        $domain = (string) config('kanban.domain');

        return $domain !== '' && strcasecmp($request->getHost(), $domain) === 0;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::aktif($request)) {
            return $next($request);
        }

        if ($request->path() === '/') {
            return redirect()->route('kanban.beranda');
        }

        if (! $request->is(...self::BOLEH)) {
            return redirect()->away(rtrim((string) config('app.url'), '/').'/'.ltrim($request->getRequestUri(), '/'));
        }

        return $next($request);
    }
}
