<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Kota;
use App\Models\Sekolah;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth.daftar', [
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'pic_sekolah' => ['nullable', 'string', 'max:255'],
            'no_telp_pic' => ['nullable', 'string', 'max:30'],
            // kota_id = id kota (peta cabang otomatis) atau 'lainnya' (cabang null).
            'kota_id' => ['required', 'string'],
            'kota_lain' => ['nullable', 'required_if:kota_id,lainnya', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('sekolah', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($data['kota_id'] === 'lainnya') {
            $kotaNama = $data['kota_lain'];
            $cabangId = null; // admin assign cabang nanti
        } else {
            $kota = Kota::findOrFail($data['kota_id']);
            $kotaNama = $kota->nama;
            $cabangId = $kota->cabang_id;
        }

        $sekolah = Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => $data['nama'],
            'alamat' => $data['alamat'] ?? null,
            'pic_sekolah' => $data['pic_sekolah'] ?? null,
            'no_telp_pic' => $data['no_telp_pic'] ?? null,
            'kota' => $kotaNama,
            'cabang_id' => $cabangId,
            'email' => $data['email'],
            'email_guru' => $data['email'], // guru = email akun (login)
        ]);
        $sekolah->password = $data['password']; // cast 'hashed'
        $sekolah->save();

        event(new Registered($sekolah)); // kirim email verifikasi
        Auth::guard('sekolah')->login($sekolah);

        // Tampilkan kode akun (id_sekolah) ke sekolah.
        return redirect()->route('sekolah.verification.notice')
            ->with('id_sekolah', $sekolah->id_sekolah);
    }
}
