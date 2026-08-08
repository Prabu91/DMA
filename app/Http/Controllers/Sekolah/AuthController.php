<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login sekolah pakai EMAIL + password (storefront /masuk).
 * id_sekolah = kode akun, BUKAN kredensial.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth.masuk');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('sekolah')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $request->session()->regenerate();

        // Sekolah lanjut belanja di storefront (bukan portal). Intended (mis. checkout) tetap dihormati.
        return redirect()->intended(route('storefront.katalog.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('sekolah')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
