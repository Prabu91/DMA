<?php

namespace App\Http\Controllers;

use App\Models\OrderPembayaran;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming bukti bayar dari disk public. Dilewatkan controller (bukan symlink
 * langsung) agar bisa dicek akses + andal di semua lingkungan (Windows/Docker).
 */
class BuktiBayarController extends Controller
{
    public function __invoke(OrderPembayaran $pembayaran): StreamedResponse
    {
        abort_unless($pembayaran->bukti_path, 404);

        $user = auth('web')->user();
        // Boleh dilihat siapa pun yang bisa mengakses order (lintas cabang / secabang).
        abort_unless(
            $user && ($user->seesAllCabang() || $user->cabang_id === $pembayaran->order->cabang_id),
            403
        );

        abort_unless(Storage::disk('public')->exists($pembayaran->bukti_path), 404);

        return Storage::disk('public')->response($pembayaran->bukti_path);
    }
}
