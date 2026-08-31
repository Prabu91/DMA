<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan order di "sampah" yang sudah melewati masa retensi
 * (Order::TRASH_RETENTION_DAYS). Jalankan terjadwal (mis. harian) atau manual.
 */
class PurgeTrashedOrders extends Command
{
    protected $signature = 'orders:purge-trash {--days= : Override masa retensi (hari)}';

    protected $description = 'Hapus permanen order di sampah yang lewat masa retensi';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: Order::TRASH_RETENTION_DAYS);
        $batas = now()->subDays($days);

        $orders = Order::onlyTrashed()->where('deleted_at', '<', $batas)->get();

        if ($orders->isEmpty()) {
            $this->info("Tidak ada order sampah lebih tua dari {$days} hari.");

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            DB::transaction(function () use ($order) {
                $order->items()->delete();
                $order->timEvent()->detach();
                $order->pembayaran()->delete();
                $order->activities()->delete();
                $order->forceDelete();
            });
        }

        $this->info("Dihapus permanen: {$orders->count()} order (sampah > {$days} hari).");

        return self::SUCCESS;
    }
}
