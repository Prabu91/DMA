<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Profil akun sekolah (storefront) — kelola data kontak sendiri.
 * Read-only: id_sekolah (kode akun), email login, kota, cabang (di-assign admin).
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('sekolah.profile', ['sekolah' => Auth::guard('sekolah')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'pic_sekolah' => ['nullable', 'string', 'max:255'],
            'no_telp_pic' => ['nullable', 'string', 'max:50'],
            'maps_link' => ['nullable', 'url', 'max:255'],
        ]);

        $sekolah = Auth::guard('sekolah')->user();
        // Email guru disamakan dengan email akun (login) — bukan field terpisah.
        $data['email_guru'] = $sekolah->email;
        $sekolah->update($data);

        return back()->with('status', 'profile-updated');
    }
}
