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
        'biru' => ['Biru', 'bg-[#0C66A4]'],
        'oranye' => ['Oranye', 'bg-[#B8620A]'],
        'hijau' => ['Hijau', 'bg-[#216E4E]'],
        'merah' => ['Merah', 'bg-[#AE2E24]'],
        'ungu' => ['Ungu', 'bg-[#5E4DB2]'],
        'pink' => ['Merah muda', 'bg-[#943D73]'],
        'toska' => ['Toska', 'bg-[#206A83]'],
        'navy' => ['Navy', 'bg-[#2E3192]'],
        'abu' => ['Abu', 'bg-[#44546F]'],
    ];

    /** Label & cover: [kunci => [label, kelas latar, kelas teks]]. Teks gelap agar terbaca. */
    public const LABEL = [
        'hijau' => ['Hijau', 'bg-[#4BCE97]', 'text-[#092B1C]'],
        'kuning' => ['Kuning', 'bg-[#F5CD47]', 'text-[#332600]'],
        'oranye' => ['Oranye', 'bg-[#FEA362]', 'text-[#381D07]'],
        'merah' => ['Merah', 'bg-[#F87168]', 'text-[#3A0B08]'],
        'ungu' => ['Ungu', 'bg-[#9F8FEF]', 'text-[#1C1540]'],
        'biru' => ['Biru', 'bg-[#579DFF]', 'text-[#0A1D3B]'],
        'langit' => ['Langit', 'bg-[#6CC3E0]', 'text-[#0B2A36]'],
        'lime' => ['Lime', 'bg-[#94C748]', 'text-[#1D2A0A]'],
        'pink' => ['Merah muda', 'bg-[#E774BB]', 'text-[#3A0C28]'],
        'abu' => ['Abu', 'bg-[#8590A2]', 'text-[#0F141A]'],
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
