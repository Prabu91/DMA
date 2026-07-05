<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('sekolah.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'id_sekolah' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('sekolah')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'id_sekolah' => 'ID sekolah atau kata sandi salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('sekolah.beranda'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('sekolah')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sekolah.login');
    }
}
