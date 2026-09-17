<?php

namespace App\Support;

/**
 * Tahap papan order. Huruf mengikuti board Trello DMA supaya tim langsung
 * mengenali posisinya (C = "3. Collect Admin", E = "5. Editing", dst.).
 *
 * Tidak semua board Trello menjadi tahap:
 * - A/B (order, H-7, H-2, Hari-H) sudah dijalankan sistem → kolom turunan
 *   "Menuju event", tidak bisa diseret.
 * - D & I (Hold …) dan O (komplain) adalah TANDA pada kartu, bukan tahap.
 * - Pecahan per orang/wilayah/jalur (12.1 Riri, 10.1 Jaksel, 5.2 YB) menjadi
 *   penanggung jawab dan filter, bukan tahap sendiri.
 */
final class TahapOrder
{
    /** Kolom turunan untuk order yang Hari-H-nya belum dikonfirmasi. */
    public const MENUJU_EVENT = 'AB';

    public const SELESAI = 'P';

    /**
     * nama, keterangan, tenggat (hari setelah event; null = tanpa tenggat
     * otomatis), peran yang biasanya memegang kartu di tahap itu.
     */
    public const DAFTAR = [
        'C' => ['nama' => 'Collect admin', 'ket' => 'Berkas & seleksi foto', 'hari' => 1, 'peran' => ['admin_sales', 'operasional']],
        'E' => ['nama' => 'Editing', 'ket' => 'Dikerjakan editor', 'hari' => null, 'peran' => ['editor']],
        'F' => ['nama' => 'QC marketing', 'ket' => 'Cek hasil edit', 'hari' => 3, 'peran' => ['marketing']],
        'G' => ['nama' => 'Produksi', 'ket' => 'Cetak & rakit', 'hari' => 4, 'peran' => ['operasional']],
        'H' => ['nama' => 'Cetak ulang', 'ket' => 'Perbaikan hasil cetak', 'hari' => null, 'peran' => ['operasional']],
        'J' => ['nama' => 'Delivery', 'ket' => 'Kirim ke sekolah', 'hari' => null, 'peran' => ['operasional']],
        'K' => ['nama' => 'Piutang', 'ket' => 'Menunggu pelunasan', 'hari' => null, 'peran' => ['admin_sales']],
        'L' => ['nama' => 'Validasi lunas', 'ket' => 'Cek pelunasan', 'hari' => null, 'peran' => ['admin_sales']],
        'M' => ['nama' => 'Kirim file', 'ket' => 'Softfile ke sekolah', 'hari' => null, 'peran' => ['admin_sales']],
        'N' => ['nama' => 'Validasi finance', 'ket' => 'Tutup buku order', 'hari' => null, 'peran' => ['admin_sales']],
        'P' => ['nama' => 'Selesai', 'ket' => 'Order tuntas', 'hari' => null, 'peran' => []],
    ];

    /** Peran yang boleh memindah kartu ke tahap mana pun. */
    public const PERAN_PENGELOLA = ['super_admin', 'operasional', 'admin_sales'];

    public static function ada(?string $tahap): bool
    {
        return $tahap !== null && array_key_exists($tahap, self::DAFTAR);
    }

    public static function nama(?string $tahap): string
    {
        if ($tahap === self::MENUJU_EVENT || $tahap === null) {
            return 'Menuju event';
        }

        return self::DAFTAR[$tahap]['nama'] ?? $tahap;
    }

    /** "E · Editing" */
    public static function label(?string $tahap): string
    {
        return self::ada($tahap) ? $tahap.' · '.self::nama($tahap) : self::nama($tahap);
    }

    /** @return array<string, string> [huruf => label] untuk pilihan */
    public static function pilihan(): array
    {
        $out = [];
        foreach (array_keys(self::DAFTAR) as $h) {
            $out[$h] = self::label($h);
        }

        return $out;
    }
}
