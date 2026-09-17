<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Kanban\SinkronOrder;
use Illuminate\Console\Command;

/**
 * Buat kartu board Order untuk order yang belum punya kartu (mis. order lama
 * sebelum kanban ada). Aman dijalankan berulang.
 */
class KanbanSinkronOrder extends Command
{
    protected $signature = 'kanban:sinkron-order
        {--sejak= : Hanya order yang dibuat sejak tanggal ini (YYYY-MM-DD)}
        {--termasuk-selesai : Sertakan order yang event-nya sudah selesai}';

    protected $description = 'Buat/perbarui kartu board Order dari data order';

    public function handle(SinkronOrder $sinkron): int
    {
        $jumlah = 0;

        Order::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->when($this->option('sejak'), fn ($q, $tgl) => $q->where('created_at', '>=', $tgl))
            ->unless($this->option('termasuk-selesai'), fn ($q) => $q->where(fn ($w) => $w->whereNull('event_status')->orWhere('event_status', '!=', 'selesai')))
            ->whereDoesntHave('kartuKanban')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($sinkron, &$jumlah) {
                foreach ($orders as $order) {
                    if ($sinkron->sinkron($order)) {
                        $jumlah++;
                    }
                }
            });

        $this->info("Kartu dibuat: {$jumlah}.");

        return self::SUCCESS;
    }
}
