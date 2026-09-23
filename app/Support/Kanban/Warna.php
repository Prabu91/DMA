<?php

namespace App\Support\Kanban;

/**
 * Palet warna kanban. Kelas Tailwind ditulis lengkap di sini (bukan dirakit)
 * supaya ikut terbaca saat build — tailwind.config memindai app/Support.
 */
final class Warna
{
    /** Latar board: [kunci => [label, kelas latar]]. Cukup gelap untuk teks putih. */
    public const BOARD = [
        'biru' => ['Blue', 'bg-[#0C66A4]'],
        'oranye' => ['Orange', 'bg-[#B8620A]'],
        'hijau' => ['Green', 'bg-[#216E4E]'],
        'merah' => ['Red', 'bg-[#AE2E24]'],
        'ungu' => ['Purple', 'bg-[#5E4DB2]'],
        'pink' => ['Pink', 'bg-[#943D73]'],
        'toska' => ['Teal', 'bg-[#206A83]'],
        'navy' => ['Navy', 'bg-[#2E3192]'],
        'abu' => ['Grey', 'bg-[#44546F]'],
    ];

    /** Label & cover: [kunci => [label, kelas latar, kelas teks]]. Teks gelap agar terbaca. */
    public const LABEL = [
        'hijau' => ['Green', 'bg-[#4BCE97]', 'text-[#092B1C]'],
        'kuning' => ['Yellow', 'bg-[#F5CD47]', 'text-[#332600]'],
        'oranye' => ['Orange', 'bg-[#FEA362]', 'text-[#381D07]'],
        'merah' => ['Red', 'bg-[#F87168]', 'text-[#3A0B08]'],
        'ungu' => ['Purple', 'bg-[#9F8FEF]', 'text-[#1C1540]'],
        'biru' => ['Blue', 'bg-[#579DFF]', 'text-[#0A1D3B]'],
        'langit' => ['Sky', 'bg-[#6CC3E0]', 'text-[#0B2A36]'],
        'lime' => ['Lime', 'bg-[#94C748]', 'text-[#1D2A0A]'],
        'pink' => ['Pink', 'bg-[#E774BB]', 'text-[#3A0C28]'],
        'abu' => ['Grey', 'bg-[#8590A2]', 'text-[#0F141A]'],
    ];

    public static function board(?string $kunci): string
    {
        return (self::BOARD[$kunci] ?? self::BOARD['biru'])[1];
    }

    public static function label(?string $kunci): string
    {
        $w = self::LABEL[$kunci] ?? self::LABEL['abu'];

        return $w[1].' '.$w[2];
    }

    public static function labelLatar(?string $kunci): string
    {
        return (self::LABEL[$kunci] ?? self::LABEL['abu'])[1];
    }

    public static function namaLabel(?string $kunci): string
    {
        return (self::LABEL[$kunci] ?? self::LABEL['abu'])[0];
    }

    /** Label bawaan board baru — sama seperti enam label awal Trello. */
    public const LABEL_AWAL = ['hijau', 'kuning', 'oranye', 'merah', 'ungu', 'biru'];
}
