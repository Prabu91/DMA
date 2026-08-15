<?php

namespace App\Http\Controllers\Sekolah;

use App\Http\Controllers\Controller;
use App\Models\Kecamatan;
use App\Models\Kota;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('storefront.auth.daftar', [
            'kotaOptions' => Kota::orderBy('nama')->get(['id', 'nama']),
            // Kecamatan dikelompokkan per kota (untuk dependent select di form).
            'kecamatanByKota' => Kecamatan::orderBy('nama')->get(['id', 'nama', 'kota_id'])
                ->groupBy('kota_id')
                ->map(fn ($g) => $g->map(fn ($k) => ['id' => $k->id, 'nama' => $k->nama])->values())
                ->all(),
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
            // Kecamatan opsional; bila diisi harus milik kota terpilih (divalidasi di bawah).
            'kecamatan_id' => ['nullable', 'integer', Rule::exists('kecamatan', 'id')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('sekolah', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Cegah duplikat: kombinasi nama + PIC + no. telp + alamat harus unik.
        if (Sekolah::comboExists([
            'nama' => $data['nama'],
            'pic_sekolah' => $data['pic_sekolah'] ?? null,
            'no_telp_pic' => $data['no_telp_pic'] ?? null,
            'alamat' => $data['alamat'] ?? null,
        ])) {
            throw ValidationException::withMessages([
                'nama' => 'Data sekolah (nama, PIC, no. telp, alamat) sudah terdaftar.',
            ]);
        }

        $kecamatanId = null;

        if ($data['kota_id'] === 'lainnya') {
            $kotaNama = $data['kota_lain'];
            $cabangId = null; // admin assign cabang nanti
        } else {
            $kota = Kota::findOrFail($data['kota_id']);
            $kotaNama = $kota->nama;
            $cabangId = $kota->cabang_id;

            // Kecamatan hanya sah bila milik kota terpilih.
            if (! empty($data['kecamatan_id'])
                && Kecamatan::where('id', $data['kecamatan_id'])->where('kota_id', $kota->id)->exists()) {
                $kecamatanId = (int) $data['kecamatan_id'];
            }
        }

        $sekolah = Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => $data['nama'],
            'alamat' => $data['alamat'] ?? null,
            'pic_sekolah' => $data['pic_sekolah'] ?? null,
            'no_telp_pic' => $data['no_telp_pic'] ?? null,
            'kota' => $kotaNama,
            'kecamatan_id' => $kecamatanId,
            'cabang_id' => $cabangId,
            'email' => $data['email'],
            'email_guru' => $data['email'], // guru = email akun (login)
        ]);
        $sekolah->password = $data['password']; // cast 'hashed'
        $sekolah->save();

        // Verifikasi email TIDAK diwajibkan (sementara; nanti via WA) → langsung login & belanja.
        Auth::guard('sekolah')->login($sekolah);

        // Tampilkan ID sekolah (kredensial login) ke sekolah.
        return redirect()->route('storefront.katalog.index')
            ->with('id_sekolah', $sekolah->id_sekolah);
    }
}
