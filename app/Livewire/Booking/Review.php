<?php

namespace App\Livewire\Booking;

use App\Services\BookingService;
use App\Support\BookingContext;
use App\Support\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Review extends Component
{
    public string $konteks = 'staf'; // 'staf' | 'sekolah' | 'publik'

    public ?string $error = null;

    // Input jumlah siswa (konteks publik: diisi di checkout, bukan di keranjang).
    public ?int $jumlahSiswaInput = null;

    // Jadwal event — diinput customer saat checkout (marketing bisa ubah nanti).
    public ?string $tanggalEvent = null;

    public ?string $jamEvent = null;

    public function mount(string $konteks = 'staf'): void
    {
        $this->konteks = in_array($konteks, ['sekolah', 'publik'], true) ? $konteks : 'staf';
        $this->jumlahSiswaInput = app(Cart::class)->jumlahSiswa() ?: null;
    }

    public function updatedJumlahSiswaInput($value): void
    {
        $n = max(0, (int) $value);
        app(Cart::class)->setJumlahSiswa($n);
        unset($this->jumlahSiswa, $this->freeItems, $this->valid);
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
        return $this->siapReview
            && $this->jumlahSiswa >= 1;
    }

    /**
     * Siap tampil review (tanpa syarat jumlah siswa). Konteks publik memakai ini
     * agar input jumlah siswa tetap tampil walau belum diisi.
     */
    #[Computed]
    public function siapReview(): bool
    {
        return $this->lines !== []
            && $this->ctx['sekolah_id'] !== null
            && $this->ctx['cabang_id'] !== null;
    }

    public function keranjangUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.keranjang'),
            'publik' => route('storefront.keranjang'),
            default => route('app.keranjang'),
        };
    }

    public function simpan()
    {
        if (! $this->valid) {
            $this->error = $this->siapReview && $this->jumlahSiswa < 1
                ? 'Isi jumlah siswa terlebih dahulu.'
                : 'Data belum lengkap. Kembali ke keranjang untuk melengkapi.';

            return null;
        }

        // Tanggal event wajib (≥ hari ini); jam opsional.
        $this->validate([
            'tanggalEvent' => ['required', 'date', 'after_or_equal:today'],
            'jamEvent' => ['nullable', 'string', 'max:20'],
        ], [
            'tanggalEvent.required' => 'Tanggal pelaksanaan event wajib diisi.',
            'tanggalEvent.after_or_equal' => 'Tanggal event tidak boleh di masa lalu.',
        ]);

        $this->error = null;

        $order = app(BookingService::class)->simpan(
            $this->ctx,
            $this->lines,
            $this->freeItems,
            $this->jumlahSiswa,
            $this->subtotal,
            $this->tanggalEvent,
            $this->jamEvent,
        );

        app(Cart::class)->clear();

        return $this->redirect(
            match ($this->konteks) {
                'sekolah', 'publik' => route('sekolah.riwayat.show', $order->id),
                default => route('app.order.show', $order->id),
            },
            navigate: true
        );
    }

    public function render()
    {
        return view('livewire.booking.review');
    }
}
