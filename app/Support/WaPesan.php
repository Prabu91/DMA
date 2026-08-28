<?php

namespace App\Support;

use App\Models\Order;

/**
 * Template pesan WhatsApp (Fonnte) — SATU tempat untuk semua teks agar mudah
 * diedit & konsisten. Dipakai via Order::kirimWa(WaPesan::xxx($order)).
 *
 * Cara ubah pesan: cukup edit string di method yang sesuai di file ini.
 * Placeholder aman terhadap data kosong (pakai fallback).
 */
class WaPesan
{
    private static function pic(Order $order): string
    {
        return $order->sekolah?->pic_sekolah ?: 'Bapak/Ibu';
    }

    private static function sekolah(Order $order): string
    {
        return $order->sekolah?->nama ?? 'sekolah Anda';
    }

    private static function tanggalEvent(Order $order): string
    {
        // Paksa Bahasa Indonesia agar konsisten walau locale app berbeda.
        return $order->tanggal_event
            ? $order->tanggal_event->locale('id')->translatedFormat('l, d F Y')
            : '(tanggal menyusul)';
    }

    /** OTP penyelesaian event — dibacakan guru ke tim event di lokasi. */
    public static function otp(Order $order, string $code): string
    {
        return "*DMA — Kode OTP Penyelesaian Event*\n\n"
            .'Halo '.self::pic($order).', kode OTP untuk menyelesaikan event foto '
            .self::sekolah($order)." adalah:\n\n*{$code}*\n\n"
            .'Berlaku '.Order::OTP_EXPIRY_MINUTES.' menit. Mohon bacakan kode ini kepada tim DMA di lokasi.';
    }

    /** Pengingat H-7 (dikirim saat admin sales konfirmasi milestone H-7). */
    public static function h7(Order $order): string
    {
        return "*DMA — Pengingat H-7 Event Foto*\n\n"
            .'Halo '.self::pic($order).', event foto '.self::sekolah($order)
            .' dijadwalkan pada *'.self::tanggalEvent($order)."*.\n\n"
            .'Tim DMA telah mengonfirmasi persiapan H-7. Mohon konfirmasi kesiapan sekolah. Terima kasih.';
    }

    /** Pengingat H-2 (dikirim saat admin sales konfirmasi milestone H-2). */
    public static function h2(Order $order): string
    {
        return "*DMA — Pengingat H-2 Event Foto*\n\n"
            .'Halo '.self::pic($order).', event foto '.self::sekolah($order)
            .' dijadwalkan pada *'.self::tanggalEvent($order)."*.\n\n"
            .'Tim DMA telah mengonfirmasi persiapan H-2. Mohon pastikan seluruh persiapan sekolah sudah siap. Terima kasih.';
    }

    /** Konfirmasi Hari-H (dikirim saat tim event konfirmasi tiba di lokasi). */
    public static function hariH(Order $order): string
    {
        return "*DMA — Konfirmasi Hari-H*\n\n"
            .'Halo '.self::pic($order).', tim DMA telah mengonfirmasi Hari-H untuk event foto '
            .self::sekolah($order).'. Sesi pemotretan akan segera dimulai. Terima kasih.';
    }

    /** Follow-up H+1 (sehari setelah event) — ucapan terima kasih & info hasil. */
    public static function h1(Order $order): string
    {
        return "*DMA — Terima Kasih*\n\n"
            .'Halo '.self::pic($order).', terima kasih atas kepercayaan '.self::sekolah($order)
            .' pada layanan foto DMA. Hasil foto sedang kami proses dan akan kami informasikan lebih lanjut.'
            ."\n\nBila ada masukan atau pertanyaan, jangan ragu membalas pesan ini. Salam hangat, Tim DMA.";
    }
}
