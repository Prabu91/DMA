<?php

namespace App\Support;

use App\Models\Sekolah;

/**
 * Menentukan konteks booking dari guard yang aktif.
 *
 * - Guard sekolah  => jalur 'sekolah' (booking mandiri untuk dirinya).
 * - Staf (web)     => jalur 'marketing' (dibuatkan staf; pilih sekolah dari cabangnya).
 *
 * @return array{sumber:string, sekolah:?Sekolah, sekolah_id:?int, marketing_id:?int, cabang_id:?int}
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
            ];
        }

        $user = auth()->user();
        $sekolahId = $cart->sekolahId();
        // Sekolah::find kena CabangScope (staf hanya cabangnya; super/operasional semua).
        $sekolah = $sekolahId ? Sekolah::find($sekolahId) : null;

        return [
            'sumber' => 'marketing',
            'sekolah' => $sekolah,
            'sekolah_id' => $sekolah?->id,
            'marketing_id' => $user?->id,
            'cabang_id' => $user?->cabang_id,
        ];
    }
}
