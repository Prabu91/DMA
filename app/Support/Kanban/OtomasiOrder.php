<?php

namespace App\Support\Kanban;

use App\Models\Order;
use App\Support\Pengaturan;

/**
 * Otomasi board Order (padanan Butler di Trello): saat order mencapai sebuah
 * milestone, kartunya pindah sendiri ke list yang ditentukan admin.
 *
 * Aturannya disimpan satu baris di pengaturan: {pemicu: id_list}.
 */
final class OtomasiOrder
{
    public const KUNCI = 'kanban_otomasi_order';

    /** Pemicu yang tersedia, berurutan seperti alur order. */
    public const PEMICU = [
        'dp' => 'Deposit paid',
        'lunas' => 'Order fully paid',
        'h7' => 'H-7 confirmed',
        'h2' => 'H-2 confirmed',
        'hari_h' => 'Event day confirmed (event done)',
        'sampai_kantor' => 'Material arrived at the office',
    ];

    /** Kolom order yang perlu diawasi supaya pemicu terdeteksi. */
    public const KOLOM_DIAWASI = [
        'status', 'konfirmasi_h7_at', 'konfirmasi_h2_at', 'konfirmasi_hh_at', 'sampai_kantor_at',
    ];

    /** @return array<string, int> pemicu => id list tujuan */
    public static function aturan(): array
    {
        $isi = json_decode((string) Pengaturan::teks(self::KUNCI), true);

        if (! is_array($isi)) {
            return [];
        }

        return collect($isi)
            ->only(array_keys(self::PEMICU))
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @param  array<string, int|string|null>  $aturan */
    public static function simpan(array $aturan): void
    {
        $bersih = collect($aturan)
            ->only(array_keys(self::PEMICU))
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->all();

        $bersih ? Pengaturan::set(self::KUNCI, json_encode($bersih)) : Pengaturan::hapus(self::KUNCI);
    }

    public static function tujuan(string $pemicu): ?int
    {
        return self::aturan()[$pemicu] ?? null;
    }

    /**
     * Pemicu yang baru saja terjadi pada order (dipanggil sesudah order
     * disimpan, saat perubahan atributnya masih terbaca).
     *
     * @return array<int, string>
     */
    public static function pemicuOrder(Order $order): array
    {
        $pemicu = [];

        if ($order->wasChanged('status')) {
            if ($order->status === 'dp') {
                $pemicu[] = 'dp';
            }
            if ($order->status === 'lunas') {
                $pemicu[] = 'lunas';
            }
        }

        foreach (['konfirmasi_h7_at' => 'h7', 'konfirmasi_h2_at' => 'h2', 'konfirmasi_hh_at' => 'hari_h', 'sampai_kantor_at' => 'sampai_kantor'] as $kolom => $nama) {
            if ($order->wasChanged($kolom) && $order->{$kolom} !== null) {
                $pemicu[] = $nama;
            }
        }

        return $pemicu;
    }

    public static function label(string $pemicu): string
    {
        return self::PEMICU[$pemicu] ?? $pemicu;
    }
}
