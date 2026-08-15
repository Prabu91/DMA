<?php

namespace App\Services;

use App\Models\Sekolah;
use App\Models\User;

/**
 * Pemetaan order sekolah → marketing berdasarkan KECAMATAN sekolah.
 * Dipakai saat checkout jalur sekolah untuk auto-assign (admin tetap bisa
 * override lewat kotak masuk). Fallback: null → order tetap menunggu di
 * kotak masuk cabang.
 */
class MarketingRouter
{
    /**
     * Cari marketing yang menangani kecamatan sekolah (dan secabang dengan
     * sekolah). Bila >1 kandidat, pilih yang beban order aktifnya paling
     * sedikit (deterministik: lalu urut id) untuk pemerataan.
     */
    public function forSekolah(Sekolah $sekolah): ?User
    {
        if (! $sekolah->kecamatan_id || ! $sekolah->cabang_id) {
            return null;
        }

        return User::role('marketing')
            ->where('cabang_id', $sekolah->cabang_id)
            ->whereHas('kecamatan', fn ($q) => $q->whereKey($sekolah->kecamatan_id))
            ->withCount(['ordersAsMarketing as beban_aktif' => fn ($q) => $q->whereIn('status', ['baru', 'dp'])])
            ->orderBy('beban_aktif')
            ->orderBy('id')
            ->first();
    }
}
