<?php

namespace App\Livewire\Booking;

use App\Services\BookingService;
use App\Support\BookingContext;
use App\Support\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Review extends Component
{
    public string $konteks = 'staf';

    public ?string $error = null;

    public function mount(string $konteks = 'staf'): void
    {
        $this->konteks = $konteks === 'sekolah' ? 'sekolah' : 'staf';
    }

    #[Computed]
    public function ctx(): array
    {
        return BookingContext::resolve(app(Cart::class));
    }

    #[Computed]
    public function lines(): array
    {
        return app(BookingService::class)->resolveLines(app(Cart::class));
    }

    #[Computed]
    public function subtotal(): int
    {
        return app(BookingService::class)->subtotal($this->lines);
    }

    #[Computed]
    public function jumlahSiswa(): int
    {
        return app(Cart::class)->jumlahSiswa();
    }

    #[Computed]
    public function freeItems(): array
    {
        return app(BookingService::class)->evaluasiFree($this->lines, $this->jumlahSiswa, $this->subtotal);
    }

    #[Computed]
    public function total(): int
    {
        return $this->subtotal; // item free = 0
    }

    #[Computed]
    public function valid(): bool
    {
        return $this->lines !== []
            && $this->ctx['sekolah_id'] !== null
            && $this->ctx['cabang_id'] !== null
            && $this->jumlahSiswa >= 1;
    }

    public function keranjangUrl(): string
    {
        return $this->konteks === 'sekolah' ? route('sekolah.keranjang') : route('keranjang');
    }

    public function simpan()
    {
        if (! $this->valid) {
            $this->error = 'Data belum lengkap. Kembali ke keranjang untuk melengkapi.';

            return null;
        }

        $order = app(BookingService::class)->simpan(
            $this->ctx,
            $this->lines,
            $this->freeItems,
            $this->jumlahSiswa,
            $this->subtotal,
        );

        app(Cart::class)->clear();

        return $this->redirect(
            $this->konteks === 'sekolah'
                ? route('sekolah.riwayat.show', $order->id)
                : route('order.show', $order->id),
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.booking.review');
    }
}
