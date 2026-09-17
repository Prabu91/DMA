<?php

namespace App\Support\Kanban;

/**
 * Posisi pecahan untuk urutan kolom & kartu (cara Trello): barang yang
 * dipindah diberi nilai di antara dua tetangganya, jadi hanya satu baris yang
 * berubah. Bila celahnya sudah terlalu sempit, pemanggil menomori ulang.
 */
final class Posisi
{
    public const JARAK = 65536.0;

    private const CELAH_MIN = 1e-6;

    /**
     * Posisi untuk disisipkan di indeks $indeks di antara $urutan (posisi
     * yang sudah ada, terurut, TANPA barang yang dipindah). Null = celah
     * terlalu sempit, nomori ulang lalu hitung lagi.
     *
     * @param  array<int, float>  $urutan
     */
    public static function untukIndeks(array $urutan, int $indeks): ?float
    {
        $urutan = array_values($urutan);
        $indeks = max(0, min($indeks, count($urutan)));
        $sebelum = $urutan[$indeks - 1] ?? null;
        $sesudah = $urutan[$indeks] ?? null;

        if ($sebelum === null && $sesudah === null) {
            return self::JARAK;
        }
        if ($sebelum === null) {
            $hasil = $sesudah / 2;

            return $hasil < self::CELAH_MIN ? null : $hasil;
        }
        if ($sesudah === null) {
            return $sebelum + self::JARAK;
        }

        $tengah = ($sebelum + $sesudah) / 2;

        return ($tengah - $sebelum) < self::CELAH_MIN ? null : $tengah;
    }

    /** Posisi baru yang rapi untuk $jumlah barang: 65536, 131072, … */
    public static function berurutan(int $jumlah): array
    {
        return array_map(fn ($i) => ($i + 1) * self::JARAK, range(0, max(0, $jumlah - 1)));
    }
}
