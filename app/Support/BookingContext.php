<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Sekolah;

/**
 * Menentukan konteks booking dari guard yang aktif.
 *
 * - Guard sekolah  => jalur 'sekolah' (booking mandiri untuk dirinya).
 * - Staf (web)     => jalur 'marketing' (dibuatkan staf; pilih sekolah dari cabangnya).
 * - Mode susulan   => mengikuti order induk (lihat Cart::mulaiSusulan).
 *
 * @return array{sumber:string, sekolah:?Sekolah, sekolah_id:?int, marketing_id:?int, cabang_id:?int, induk:?Order}
 */
class BookingContext
{
    public static function resolve(Cart $cart): array
    {
        if (auth('sekolah')->check()) {
            $sekolah = auth('sekolah')->user();

            return [
                'sumber' => 'sekolah',
                'sekolah' => $sekolah,
                'sekolah_id' => $sekolah->id,
                'marketing_id' => null,
                'cabang_id' => $sekolah->cabang_id,
                'induk' => null,
            ];
        }

        $user = auth()->user();

        // Mode susulan: semuanya mengikuti order induk, bukan pengguna yang
        // sedang login — admin pusat tidak punya cabang, dan susulan tetap
        // milik marketing & cabang event aslinya. Order::find kena CabangScope,
        // jadi induk di luar jangkauan pengguna tidak terbaca.
        $induk = $cart->indukId() ? Order::with('sekolah')->find($cart->indukId()) : null;
        if ($induk) {
            return [
                'sumber' => 'marketing',
                'sekolah' => $induk->sekolah,
                'sekolah_id' => $induk->sekolah_id,
                'marketing_id' => $induk->marketing_id ?? $user?->id,
                'cabang_id' => $induk->cabang_id,
                'induk' => $induk,
            ];
        }

        $sekolahId = $cart->sekolahId();
        // Sekolah::find kena CabangScope (staf hanya cabangnya; super/operasional semua).
        $sekolah = $sekolahId ? Sekolah::find($sekolahId) : null;

        return [
            'sumber' => 'marketing',
            'sekolah' => $sekolah,
            'sekolah_id' => $sekolah?->id,
            'marketing_id' => $user?->id,
            'cabang_id' => $user?->cabang_id,
            'induk' => null,
        ];
    }
}
