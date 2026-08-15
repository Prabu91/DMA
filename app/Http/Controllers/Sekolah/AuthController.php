<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login sekolah pakai ID SEKOLAH (mis. SKL-000001) + password (storefront /masuk).
 * id_sekolah = kredensial login. Email hanya untuk notifikasi/OTP (future WA).
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
            'id_sekolah' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Normalisasi: ID sekolah disamakan huruf besar (SKL-000001).
        $credentials['id_sekolah'] = strtoupper(trim($credentials['id_sekolah']));

        if (! Auth::guard('sekolah')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'id_sekolah' => 'ID sekolah atau kata sandi salah.',
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
