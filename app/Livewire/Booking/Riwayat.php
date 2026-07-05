<?php

namespace App\Livewire\Booking;

use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Riwayat order milik sekolah yang login (isolasi eksplisit per sekolah_id).
 */
class Riwayat extends Component
{
    #[Computed]
    public function orders()
    {
        return Order::where('sekolah_id', auth('sekolah')->id())
            ->withCount('items')
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.booking.riwayat');
    }
}
