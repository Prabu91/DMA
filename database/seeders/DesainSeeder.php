<?php

namespace Database\Seeders;

use App\Models\Desain;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

/**
 * Pool desain representatif untuk kategori bertema (pakai_desain=true) dari
 * pricelist 2026-2027. Tanpa gambar (foto_preview null → placeholder di UI).
 * Tahun ajaran aktif = 2026/2027. Dipakai saat booking produk berdesain
 * (EtalaseDetail menampilkan pool sesuai kategori + tahun ajaran).
 */
class DesainSeeder extends Seeder
{
    private const TAHUN_AJARAN = '2026/2027';

    public function run(): void
    {
        // [nama kategori => [prefix kode, [seri...]]]
        $peta = [
            // Kategori demo lama (dari KategoriSeeder/ProdukSeeder).
            'Foto Kelas' => ['FK', ['Klasik', 'Ceria', 'Formal']],
            'Foto Individu' => ['FI', ['Studio', 'Outdoor', 'Casual']],
            'Album' => ['ALB', ['Hardcover', 'Magazine', 'Kolase']],

            // Kategori bertema dari pricelist 2026-2027.
            'Wisuda' => ['WIS', ['Klasik', 'Elegan', 'Modern', 'Formal']],
            'Angkatan' => ['ANG', ['Kompak', 'Dinamis', 'Ceria', 'Formal']],
            'Manasik' => ['MAN', ['Kabah', 'Masjid', 'Padang Pasir']],
            'OB' => ['OB', ['Graduation', 'Natal', 'Sporty', 'Profesi', 'Tema']],
            'Kartini' => ['KAR', ['Batik', 'Kebaya']],
            'Profesi' => ['PRO', ['Dokter', 'Pilot', 'Polisi']],
            'Karakter' => ['KRK', ['Superhero', 'Kartun', 'Fantasi']],
            'Yearbook' => ['YB', ['Minimalis', 'Pop Art', 'Elegan']],
        ];

        foreach ($peta as $namaKategori => [$prefix, $seriList]) {
            $kategori = Kategori::where('nama', $namaKategori)->first();

            if ($kategori === null) {
                continue;
            }

            foreach ($seriList as $i => $seri) {
                $kode = sprintf('%s-%02d', $prefix, $i + 1);

                Desain::firstOrCreate(
                    ['kode' => $kode, 'kategori_id' => $kategori->id],
                    [
                        'kategori_id' => $kategori->id,
                        'seri' => $seri,
                        'orientasi' => 'portrait',
                        'foto_preview' => null,
                        'tahun_ajaran' => self::TAHUN_AJARAN,
                        'status' => 'aktif',
                    ]
                );
            }
        }
    }
}
