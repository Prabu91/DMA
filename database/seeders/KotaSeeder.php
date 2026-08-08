<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Kota;
use Illuminate\Database\Seeder;

class KotaSeeder extends Seeder
{
    /**
     * Peta kota → cabang.
     * CATATAN: Cirebon di-skip dulu (cabang belum ditentukan).
     */
    private const PETA = [
        'Bandung' => 'Bandung',
        'Cianjur' => 'Cianjur',
        'Bogor' => 'Bogor',
        'Jakarta' => 'Jaksel',
        'Tangerang' => 'Jaksel',
        'Depok' => 'Jaksel',
        'Cikarang' => 'Bekasi',
    ];

    /** kode_area untuk cabang yang dibuat dari peta. */
    private const KODE_AREA = [
        'Bandung' => 'BDG',
        'Cianjur' => 'CJR',
        'Bogor' => 'BGR',
        'Jaksel' => 'JKS',
        'Bekasi' => 'BKS',
    ];

    public function run(): void
    {
        // Pastikan cabang yang direferensikan ada.
        foreach (array_unique(array_filter(self::PETA)) as $namaCabang) {
            Cabang::firstOrCreate(
                ['nama' => $namaCabang],
                ['kode_area' => self::KODE_AREA[$namaCabang] ?? null],
            );
        }

        // Buat kota + tautkan ke cabang.
        foreach (self::PETA as $namaKota => $namaCabang) {
            $cabangId = $namaCabang
                ? Cabang::where('nama', $namaCabang)->value('id')
                : null;

            Kota::firstOrCreate(['nama' => $namaKota], ['cabang_id' => $cabangId]);
        }
    }
}
