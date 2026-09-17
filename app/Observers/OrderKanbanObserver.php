<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Kanban\SinkronOrder;
use Throwable;

/**
 * Setiap order punya kartu di board Order. Kegagalan sinkron hanya dilaporkan
 * — tidak boleh menggagalkan pembuatan/perubahan order.
 */
class OrderKanbanObserver
{
    private const KOLOM_PENTING = ['sekolah_id', 'marketing_id', 'status', 'order_induk_id'];

    public function created(Order $order): void
    {
        $this->sinkron($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged(self::KOLOM_PENTING)) {
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
