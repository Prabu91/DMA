<?php

namespace App\Livewire\Booking;

use App\Models\Sekolah;
use App\Services\BookingService;
use App\Support\Cart;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Keranjang booking — mesin bersama jalur staf & sekolah.
 * Konteks jalur ditentukan dari guard:
 *  - guard sekolah  => sumber 'sekolah', booking untuk dirinya sendiri.
 *  - staf (web)     => sumber 'marketing', pilih sekolah dari cabangnya.
 */
class Keranjang extends Component
{
    public string $konteks = 'staf'; // 'staf' | 'sekolah' | 'publik'

    public int $jumlahSiswa = 0;

    public ?int $sekolahId = null; // hanya jalur marketing

    public ?string $info = null;

    public function mount(string $konteks = 'staf'): void
    {
        $this->konteks = in_array($konteks, ['sekolah', 'publik'], true) ? $konteks : 'staf';
        $cart = app(Cart::class);
        $this->jumlahSiswa = $cart->jumlahSiswa();
        $this->sekolahId = $cart->sekolahId();
    }

    #[Computed]
    public function isSekolahFlow(): bool
    {
        return auth('sekolah')->check();
    }

    #[Computed]
    public function sekolahOptions(): array
    {
        // CabangScope membatasi ke cabang staf (super_admin/operasional lihat semua).
        return Sekolah::orderBy('nama')->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function sekolahTerpilih(): ?Sekolah
    {
        if ($this->isSekolahFlow()) {
            return auth('sekolah')->user();
        }

        return $this->sekolahId ? Sekolah::find($this->sekolahId) : null;
    }

    /**
     * Resolusi baris keranjang + harga efektif (server-side, via BookingService).
     */
    #[Computed]
    public function lines(): array
    {
        return app(BookingService::class)->resolveLines(app(Cart::class));
    }

    #[Computed]
    public function subtotal(): int
    {
        return (int) array_sum(array_column($this->lines(), 'total'));
    }

    public function updatedJumlahSiswa($value): void
    {
        $this->jumlahSiswa = max(0, (int) $value);
        app(Cart::class)->setJumlahSiswa($this->jumlahSiswa);
    }

    public function updatedSekolahId($value): void
    {
        $this->sekolahId = $value ? (int) $value : null;
        app(Cart::class)->setSekolahId($this->sekolahId);
    }

    public function ubahQty(string $key, int $qty): void
    {
        app(Cart::class)->updateQty($key, $qty);
        unset($this->lines, $this->subtotal);
    }

    public function hapus(string $key): void
    {
        app(Cart::class)->remove($key);
        unset($this->lines, $this->subtotal);
    }

    public function kosongkan(): void
    {
        app(Cart::class)->clear();
        $this->jumlahSiswa = 0;
        $this->sekolahId = null;
        unset($this->lines, $this->subtotal);
    }

    public function lanjut()
    {
        $this->info = null;

        if (app(Cart::class)->isEmpty()) {
            $this->info = 'Keranjang masih kosong.';

            return null;
        }

        // Konteks publik: menuju checkout. CheckoutController menerapkan login-gate
        // (redirect tamu ke /masuk dgn intended) + wajib verified + blokir cabang null.
        if ($this->konteks === 'publik') {
            return $this->redirect(route('storefront.checkout'), navigate: true);
        }

        if (! $this->sekolahTerpilih()) {
            $this->info = 'Pilih sekolah tujuan terlebih dahulu.';

            return null;
        }
        if ($this->jumlahSiswa < 1) {
            $this->info = 'Isi jumlah siswa terlebih dahulu.';

            return null;
        }

        return $this->redirect(
            $this->konteks === 'sekolah' ? route('sekolah.review') : route('app.review'),
            navigate: true
        );
    }

    public function katalogUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.katalog.index'),
            'publik' => route('storefront.katalog.index'),
            default => route('app.etalase.index'),
        };
    }

    public function render()
    {
        return view('livewire.booking.keranjang');
    }
}
