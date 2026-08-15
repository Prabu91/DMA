<?php

namespace App\Livewire\Finance;

use App\Models\Order;
use App\Support\OrderStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finance — Transaksi Harian per Event. Semua event pada tanggal terpilih
 * (hari-H). Catat DP inline (tanggal_bayar = tanggal event → tercatat sebagai
 * transaksi hari-H, bukan penagihan harian). Yang belum bayar tetap tampil.
 */
#[Layout('layouts.app')]
class TransaksiEventHarian extends Component
{
    #[Url]
    public string $tanggal = '';

    /** @var array<int,int|string|null> [order_id => nominal DP] */
    public array $inputDp = [];

    public ?string $msg = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin_sales']), 403);
        $this->tanggal = $this->tanggal ?: now()->toDateString();
    }

    /** Catat DP inline untuk sebuah order (tanggal bayar = tanggal event). */
    public function catatDp(int $orderId): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin_sales']), 403);

        $order = Order::whereKey($orderId)->whereNotNull('marketing_id')->firstOrFail();
        abort_if($order->status === OrderStatus::BATAL, 422);

        $jumlah = (int) ($this->inputDp[$orderId] ?? 0);
        if ($jumlah < 1) {
            $this->addError('inputDp.'.$orderId, 'Nominal harus ≥ 1.');

            return;
        }

        $order->pembayaran()->create([
            'jenis' => 'dp',
            'jumlah' => $jumlah,
            'tanggal_bayar' => $this->tanggal, // = tanggal event → transaksi hari-H
            'dicatat_oleh' => auth()->id(),
        ]);
        $order->load('pembayaran')->recalcStatusPembayaran();
        $order->catat('pembayaran_dp', 'Rp'.number_format($jumlah, 0, ',', '.').' (hari-H)');

        unset($this->inputDp[$orderId]);
        $this->msg = 'DP dicatat.';
    }

    public function render()
    {
        $user = auth()->user();

        $orders = Order::query()
            ->whereDate('tanggal_event', $this->tanggal)
            ->whereNotNull('marketing_id')
            ->where('status', '!=', OrderStatus::BATAL)
            ->when(! $user->seesAllCabang(), fn ($x) => $x->where('cabang_id', $user->cabang_id))
            ->with(['sekolah', 'marketing', 'pembayaran'])
            ->orderBy('id')
            ->get();

        // Total DP terkumpul pada tanggal event tsb.
        $terkumpul = $orders->sum(
            fn ($o) => $o->pembayaran
                ->filter(fn ($p) => optional($p->tanggal_bayar)->toDateString() === $this->tanggal)
                ->sum('jumlah')
        );

        return view('livewire.finance.transaksi-event-harian', [
            'orders' => $orders,
            'terkumpul' => (int) $terkumpul,
        ]);
    }
}
