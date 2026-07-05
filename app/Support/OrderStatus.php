<?php

namespace App\Support;

/**
 * PROVISIONAL — kosakata status order untuk dashboard.
 *
 * Nilai status/pembayaran final adalah domain Fase 2 (proofing, pembayaran,
 * status history) yang BELUM dibangun. Sementara itu, seluruh asumsi status
 * dikumpulkan di satu tempat ini agar mudah disesuaikan nanti tanpa memburu
 * string ke seluruh query dashboard.
 */
class OrderStatus
{
    // orders.status
    public const BARU = 'baru';     // dibuat, menunggu DP
    public const DP = 'dp';         // DP dibayar, menunggu pelunasan
    public const LUNAS = 'lunas';
    public const BATAL = 'batal';

    // orders.event_status
    public const EVENT_DIJADWALKAN = 'dijadwalkan';
    public const EVENT_SELESAI = 'selesai';
    public const EVENT_BATAL = 'batal';

    /** Order dianggap "aktif" (dalam proses, belum selesai/batal). */
    public const AKTIF = [self::BARU, self::DP];

    /** Status yang berarti masih menunggu pembayaran DP. */
    public const MENUNGGU_DP = [self::BARU];

    public static function label(?string $status): string
    {
        return match ($status) {
            self::BARU => 'Menunggu DP',
            self::DP => 'DP dibayar',
            self::LUNAS => 'Lunas',
            self::BATAL => 'Batal',
            self::EVENT_DIJADWALKAN => 'Dijadwalkan',
            self::EVENT_SELESAI => 'Selesai',
            default => $status ? ucfirst($status) : '—',
        };
    }

    /** Varian badge (lihat komponen x-badge) untuk sebuah status. */
    public static function badge(?string $status): string
    {
        return match ($status) {
            self::LUNAS, self::EVENT_SELESAI => 'success',
            self::BARU => 'pending',
            self::DP, self::EVENT_DIJADWALKAN => 'info',
            self::BATAL, self::EVENT_BATAL => 'danger',
            default => 'neutral',
        };
    }
}
