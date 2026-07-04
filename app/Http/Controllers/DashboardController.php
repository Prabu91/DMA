<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Semua role yang punya dashboard. Nama route: dashboard.{role}.
     */
    public const ROLE_DASHBOARDS = [
        'super_admin',
        'operasional',
        'area',
        'marketing',
        'tim_event',
        'editor',
    ];

    /**
     * Titik masuk setelah login: arahkan user ke dashboard sesuai role-nya.
     */
    public function index(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();

        if ($role !== null && in_array($role, self::ROLE_DASHBOARDS, true)) {
            return redirect()->route('dashboard.'.$role);
        }

        // User tanpa role yang dikenal: tampilkan dashboard generik.
        return view('dashboard', ['role' => $role]);
    }

    /**
     * Dashboard placeholder per role.
     */
    public function show(Request $request, string $role): View
    {
        $view = 'dashboard.'.$role;

        return view()->exists($view)
            ? view($view)
            : view('dashboard', ['role' => $role]);
    }
}
