<?php

namespace App\Livewire\Booking;

use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrderDetail extends Component
{
    public string $konteks = 'staf';

    public int $orderId;

    public function mount(string $konteks, int $orderId): void
    {
        $this->konteks = $konteks === 'sekolah' ? 'sekolah' : 'staf';
        $this->orderId = $orderId;
    }

    #[Computed]
    public function order(): Order
    {
        $with = ['items.produk', 'items.paket', 'items.desain', 'sekolah', 'cabang', 'marketing'];

        if ($this->konteks === 'sekolah') {
            // Isolasi eksplisit: hanya order milik sekolah yang login.
            return Order::where('sekolah_id', auth('sekolah')->id())
                ->where('id', $this->orderId)
                ->with($with)
                ->firstOrFail();
        }

        // Staf: CabangScope membatasi ke cabang staf.
        return Order::with($with)->findOrFail($this->orderId);
    }

    #[Computed]
    public function paidItems()
    {
        return $this->order->items->where('is_free', false);
    }

    #[Computed]
    public function freeItems()
    {
        return $this->order->items->where('is_free', true);
    }

    public function kembaliUrl(): string
    {
        return $this->konteks === 'sekolah'
            ? route('sekolah.riwayat.index')
            : route('etalase.index');
    }

    public function pdfUrl(): string
    {
        return $this->konteks === 'sekolah'
            ? route('sekolah.riwayat.pdf', $this->orderId)
            : route('order.pdf', $this->orderId);
    }

    public function qrSvg(): ?string
    {
        return $this->order->booking_code
            ? \App\Support\Qr::svg($this->order->booking_code, 150)
            : null;
    }

    public function render()
    {
        return view('livewire.booking.order-detail');
    }
}
