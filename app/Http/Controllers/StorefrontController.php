<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Order;
use App\Models\Paket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Storefront publik di "/". Home = katalog landing (browse terbuka tanpa login).
 * Staf (guard web) diarahkan ke panel; auth sekolah diurus terpisah.
 */
class StorefrontController extends Controller
{
    public function home(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('app.dashboard');
        }

        // Kategori aktif + produk aktifnya (untuk tab kategori bergambar ala Jonas).
        $kategoriList = Kategori::query()
            ->withCount(['produk' => fn ($q) => $q->where('status', 'aktif')])
            ->with(['produk' => fn ($q) => $q->where('status', 'aktif')->orderBy('nama')->limit(8)])
            ->orderBy('nama')
            ->get()
            ->filter(fn ($k) => $k->produk_count > 0)
            ->values();

        // Paket populer (carousel).
        $paketUnggulan = Paket::query()
            ->where('status', 'aktif')
            ->withCount('produk')
            ->orderBy('nama')
            ->limit(10)
            ->get();

        return view('storefront.home', [
            'kategoriList' => $kategoriList,
            'paketUnggulan' => $paketUnggulan,
        ]);
    }

    /**
     * Halaman verifikasi publik dari QR — /cek/{booking_code}.
     * Read-only, tanpa data sensitif (tanpa nominal/kontak). Untuk memastikan
     * booking asli terdaftar di sistem DMA.
     */
    public function cek(string $booking): View
    {
        $order = Order::query()
            ->where('booking_code', $booking)
            ->with(['sekolah:id,nama', 'cabang:id,nama'])
            ->withCount('items')
            ->first();

        return view('storefront.cek', ['order' => $order, 'booking' => $booking]);
    }
}
