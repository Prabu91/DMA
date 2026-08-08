<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Database\Seeder;

/**
 * Seeder representatif dari PRICE 2026-2027 — ≥1 produk per kategori utama
 * pelanggan. Harga & ukuran nyata dari pricelist. Gaya (Gradasi/Blok/3D/…)
 * ikut di NAMA produk (kolom `gaya` dibiarkan null). Ukuran (8R/10RP/12RP/16RP)
 * → produk_opsi (harga_override per ukuran). `satuan` menandai produk yang
 * dihitung per jumlah siswa vs per qty (Yearbook sengaja dibuat campur).
 */
class PricelistSeeder extends Seeder
{
    public function run(): void
    {
        // 'ukuran' => [nilai => harga] menjadi produk_opsi (wajib bila >1 ukuran);
        // harga produk = harga ukuran terendah. Tanpa 'ukuran' → pakai 'harga'.
        $katalog = [
            ['kategori' => 'Wisuda', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Wisuda Gradasi', 'satuan' => 'siswa', 'ukuran' => ['10RP' => 89000, '12RP' => 120000]],
                ['nama' => 'Wisuda Blok', 'satuan' => 'siswa', 'ukuran' => ['10RP' => 100000, '12RP' => 130000]],
            ]],
            ['kategori' => 'Angkatan', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Angkatan Gradasi', 'satuan' => 'siswa', 'ukuran' => ['8R' => 65000, '10RP' => 89000, '12RP' => 120000]],
                ['nama' => 'Angkatan Blok', 'satuan' => 'siswa', 'ukuran' => ['8R' => 75000, '10RP' => 95000, '12RP' => 130000]],
            ]],
            ['kategori' => 'Manasik', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Manasik Gradasi', 'satuan' => 'siswa', 'ukuran' => ['8R' => 65000, '10RP' => 89000, '12RP' => 120000]],
            ]],
            ['kategori' => 'OB', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'OB Profesi Gradasi', 'satuan' => 'siswa', 'ukuran' => ['10RP' => 89000, '12RP' => 120000]],
                ['nama' => 'OB Sporty Gradasi', 'satuan' => 'siswa', 'harga' => 89000],
            ]],
            ['kategori' => 'Kartini', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Kartini Gradasi', 'satuan' => 'siswa', 'ukuran' => ['8R' => 65000, '10RP' => 89000]],
            ]],
            ['kategori' => 'Profesi', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Profesi 3D 8R', 'satuan' => 'siswa', 'harga' => 135000],
            ]],
            ['kategori' => 'Karakter', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Karakter Gradasi 10RP', 'satuan' => 'siswa', 'harga' => 85000],
            ]],
            ['kategori' => 'Pas Foto', 'pakai_desain' => false, 'produk' => [
                ['nama' => 'Pas Foto Reguler', 'satuan' => 'siswa', 'harga' => 15000],
                ['nama' => 'Pas Foto SD/SMP/SMA', 'satuan' => 'siswa', 'harga' => 20000],
                ['nama' => 'Pas Foto 12 Pcs', 'satuan' => 'siswa', 'harga' => 22500],
            ]],
            ['kategori' => 'Yearbook', 'pakai_desain' => true, 'produk' => [
                ['nama' => 'Yearbook 30 Hal', 'satuan' => 'siswa', 'harga' => 160000],
                ['nama' => 'Yearbook 100 Hal', 'satuan' => 'siswa', 'harga' => 495000],
                ['nama' => 'Biaya Hard Cover', 'satuan' => 'qty', 'harga' => 20000],
                ['nama' => 'Penambahan Halaman YB', 'satuan' => 'qty', 'harga' => 15000],
            ]],
            ['kategori' => 'Souvenir', 'pakai_desain' => false, 'produk' => [
                ['nama' => 'Souvenir Hanging Foto', 'satuan' => 'qty', 'harga' => 85000],
                ['nama' => 'Souvenir Box Pensil', 'satuan' => 'qty', 'harga' => 50000],
                ['nama' => 'Medali Semester 2', 'satuan' => 'qty', 'harga' => 25000],
            ]],
            ['kategori' => 'Frame', 'pakai_desain' => false, 'produk' => [
                ['nama' => 'Frame Only Gradasi', 'satuan' => 'qty', 'ukuran' => ['8R' => 40000, '10RP' => 50000, '12RP' => 60000, '16RP' => 120000]],
                ['nama' => 'Frame Custom', 'satuan' => 'qty', 'harga' => 250000],
            ]],
            ['kategori' => 'Paketan', 'pakai_desain' => false, 'produk' => [
                ['nama' => 'Foto Booth Unlimited 4 Jam', 'satuan' => 'qty', 'harga' => 2000000],
                ['nama' => 'Foto Booth + Video 360', 'satuan' => 'qty', 'harga' => 4500000],
                ['nama' => 'Foto Kegiatan', 'satuan' => 'qty', 'harga' => 2500000],
                ['nama' => 'Video Shoot', 'satuan' => 'qty', 'harga' => 2700000],
            ]],
        ];

        foreach ($katalog as $blok) {
            $kategori = Kategori::firstOrCreate(
                ['nama' => $blok['kategori']],
                ['pakai_desain' => $blok['pakai_desain']]
            );

            foreach ($blok['produk'] as $p) {
                $ukuran = $p['ukuran'] ?? null;
                $harga = $ukuran ? min($ukuran) : $p['harga'];

                $produk = Produk::firstOrCreate(
                    ['nama' => $p['nama'], 'kategori_id' => $kategori->id],
                    [
                        'kategori_id' => $kategori->id,
                        'gaya' => null,
                        'harga' => $harga,
                        'satuan' => $p['satuan'],
                        'deskripsi' => $p['nama'].' — '.$blok['kategori'],
                        'status' => 'aktif',
                    ]
                );

                // Sync opsi ukuran (idempoten).
                if ($ukuran) {
                    $produk->opsi()->where('tipe_opsi', 'ukuran')->delete();
                    foreach ($ukuran as $nilai => $hrg) {
                        $produk->opsi()->create([
                            'tipe_opsi' => 'ukuran',
                            'nilai_opsi' => $nilai,
                            'harga_override' => $hrg,
                            'is_wajib' => count($ukuran) > 1,
                        ]);
                    }
                }
            }
        }
    }
}
