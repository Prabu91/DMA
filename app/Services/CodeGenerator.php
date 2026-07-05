<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Perakit booking_code: DDMMYY + cabang.kode_area + user.kode_role + urutan(3).
 * Contoh: 270626BDGMKT1001.
 *
 * Anti-bentrok: memakai advisory lock transaksional Postgres per-prefix agar
 * dua proses tidak menghasilkan urutan yang sama.
 *
 * Dipicu saat marketing_id terisi & code belum ada:
 *  - jalur marketing: langsung setelah simpan;
 *  - jalur sekolah: saat di-assign/diambil.
 */
class CodeGenerator
{
    public function generate(Order $order): ?string
    {
        if ($order->booking_code) {
            return $order->booking_code; // idempotent
        }
        if (! $order->marketing_id) {
            return null; // belum ada marketing → belum boleh dibuat
        }

        $order->loadMissing(['cabang', 'marketing']);

        $tanggal = ($order->tanggal_booking ?? now())->format('dmy');
        $area = strtoupper($order->cabang?->kode_area ?: 'XXX');
        $role = strtoupper($order->marketing?->kode_role ?: '00');
        $prefix = $tanggal.$area.$role;

        return DB::transaction(function () use ($order, $prefix) {
            // Serialize pembuatan kode untuk prefix ini (dilepas saat commit).
            DB::select('select pg_advisory_xact_lock(?)', [crc32($prefix)]);

            $max = Order::withoutGlobalScopes()
                ->where('booking_code', 'like', $prefix.'%')
                ->pluck('booking_code')
                ->map(fn ($code) => (int) substr($code, strlen($prefix)))
                ->max() ?? 0;

            $code = $prefix.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);

            $order->booking_code = $code;
            $order->save();

            return $code;
        });
    }
}
