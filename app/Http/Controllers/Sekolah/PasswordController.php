<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('sekolah.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            // Verifikasi kata sandi lama pada guard sekolah.
            'current_password' => ['required', 'current_password:sekolah'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $sekolah = Auth::guard('sekolah')->user();
        $sekolah->password = $request->password; // cast 'hashed' meng-hash otomatis
        $sekolah->save();

        return back()->with('status', 'password-updated');
    }
}
