<?php

namespace App\Livewire\Finance;

use App\Models\OrderPembayaran;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finance — Penagihan Harian. Pembayaran (pelunasan / DP telat) yang dicatat
 * pada tanggal terpilih DI LUAR hari-H event (tanggal_bayar != tanggal_event).
 * Laporan harian pembayaran sisa tagihan. Read-only.
 */
#[Layout('layouts.app')]
class PenagihanHarian extends Component
{
    #[Url]
    public string $tanggal = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin_sales']), 403);
        $this->tanggal = $this->tanggal ?: now()->toDateString();
    }

    public function render()
    {
        $user = auth()->user();

        $rows = OrderPembayaran::query()
            ->select('order_pembayaran.*')
            ->join('orders', 'orders.id', '=', 'order_pembayaran.order_id')
            ->whereDate('order_pembayaran.tanggal_bayar', $this->tanggal)
            // Bukan pembayaran hari-H event → penagihan harian.
            ->where(function ($w) {
                $w->whereNull('orders.tanggal_event')
                    ->orWhereColumn('order_pembayaran.tanggal_bayar', '!=', 'orders.tanggal_event');
            })
            ->when(! $user->seesAllCabang(), fn ($x) => $x->where('orders.cabang_id', $user->cabang_id))
            ->with(['order.sekolah', 'order.marketing', 'order.pembayaran', 'pencatat'])
            ->orderBy('order_pembayaran.created_at')
            ->get();

        return view('livewire.finance.penagihan-harian', [
            'rows' => $rows,
            'totalTerkumpul' => (int) $rows->sum('jumlah'),
        ]);
    }
}
