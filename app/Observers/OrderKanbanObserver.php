<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Kanban\SinkronOrder;
use App\Support\Kanban\OtomasiOrder;
use Throwable;

/**
 * Setiap order punya kartu di board Order. Kegagalan sinkron hanya dilaporkan
 * — tidak boleh menggagalkan pembuatan/perubahan order.
 */
class OrderKanbanObserver
{
    private const KOLOM_PENTING = ['sekolah_id', 'marketing_id', 'status', 'order_induk_id'];

    /** Kolom yang memicu otomasi board Order (lihat OtomasiOrder). */
    private function kolomDiawasi(): array
    {
        return array_unique(array_merge(self::KOLOM_PENTING, OtomasiOrder::KOLOM_DIAWASI));
    }

    public function created(Order $order): void
    {
        $this->sinkron($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged($this->kolomDiawasi())) {
            $this->sinkron($order);
        }
    }

    public function deleted(Order $order): void
    {
        $this->sinkron($order);
    }

    public function restored(Order $order): void
    {
        $this->sinkron($order);
    }

    private function sinkron(Order $order): void
    {
        try {
            app(SinkronOrder::class)->sinkron($order);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
