<?php

namespace App\Http\Controllers;

use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Login-gate checkout storefront (Fase 4). Menerapkan aturan pemilik:
 *  - Tamu → login sekolah (intended kembali ke /checkout, keranjang utuh).
 *  - Verifikasi email TIDAK diwajibkan (sementara; nanti verifikasi via WA).
 *  - cabang_id null (sekolah kota "lainnya") → diblokir; admin assign cabang dulu.
 *  - Keranjang kosong → kembali ke keranjang.
 */
class CheckoutController extends Controller
{
    public function show(): View|RedirectResponse
    {
        // Login-gate: simpan intended (/checkout) lalu arahkan ke login sekolah.
        if (! Auth::guard('sekolah')->check()) {
            return redirect()->guest(route('sekolah.masuk'));
        }

        $sekolah = Auth::guard('sekolah')->user();

        // Keranjang kosong → tak ada yang di-checkout.
        if (app(Cart::class)->isEmpty()) {
            return redirect()->route('storefront.keranjang');
        }

        // Cabang belum ditetapkan → blokir (view menampilkan pesan hubungi admin).
        return view('storefront.checkout', [
            'cabangBelumDitetapkan' => $sekolah->cabang_id === null,
        ]);
    }
}
