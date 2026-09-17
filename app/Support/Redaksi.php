<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Sekolah;

/**
 * Redaksi = teks yang ikut dicetak di produk. Isinya nama lengkap + alamat
 * sekolah (dikonfirmasi DMA), jadi dibuat dari data sekolah; order hanya
 * menyimpan koreksi bila nama cetaknya memang harus berbeda.
 */
final class Redaksi
{
    /** Redaksi yang berlaku untuk order ini (koreksi order, kalau ada). */
    public static function untuk(Order $order): string
    {
        $koreksi = trim((string) $order->redaksi);

        return $koreksi !== '' ? $koreksi : self::bawaan($order->sekolah);
    }

    /** Redaksi dari data sekolah: nama di baris pertama, alamat di baris kedua. */
    public static function bawaan(?Sekolah $sekolah): string
    {
        if (! $sekolah) {
            return '';
        }

        return implode("\n", array_filter([
            trim((string) $sekolah->nama),
            trim((string) $sekolah->alamat),
        ], fn ($baris) => $baris !== ''));
    }

    /** Order memakai koreksi, bukan redaksi dari data sekolah? */
    public static function dikoreksi(Order $order): bool
    {
        return trim((string) $order->redaksi) !== '';
    }

    /**
     * Tanda nama bermasalah. Sebagian nama di data sekolah report berakhiran
     * "_0925" / "_CIRACAS" — salah penamaan yang tidak boleh ikut tercetak.
     * Sistem tidak memotongnya diam-diam (nama asli bisa saja memuat garis
     * bawah); staf diberi peringatan untuk mengoreksi redaksinya.
     */
    public static function perluDicek(Order $order): bool
    {
        return str_contains(self::untuk($order), '_');
    }
}
