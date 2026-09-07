<?php

namespace App\Livewire;

use App\Support\Pengaturan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Saklar aplikasi yang boleh diubah admin sendiri, tanpa deploy ulang.
 * Sengaja dibatasi admin pusat karena efeknya berlaku untuk semua cabang.
 */
#[Layout('layouts.app')]
class PengaturanIndex extends Component
{
    public bool $hargaPublikDisembunyikan = false;

    public ?string $success = null;

    public ?string $error = null;

    /** Token API mentah — hanya ada di memori, ditampilkan sekali setelah dibuat. */
    public ?string $tokenBaru = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        $this->hargaPublikDisembunyikan = Pengaturan::bool(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN);
    }

    /** Token API sudah pernah dibuat? (isinya sendiri tak bisa dibaca lagi) */
    #[Computed]
    public function adaTokenApi(): bool
    {
        return Pengaturan::teks(Pengaturan::API_TOKEN_HASH) !== null;
    }

    /**
     * Buat token baru. Yang disimpan hanya hash-nya; token mentah ditampilkan
     * sekali di layar ini dan tidak bisa dilihat lagi setelah halaman berpindah.
     * Membuat token baru otomatis membatalkan token lama.
     */
    public function buatTokenApi(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        $this->tokenBaru = Str::random(64);
        Pengaturan::set(Pengaturan::API_TOKEN_HASH, Hash::make($this->tokenBaru));
        unset($this->adaTokenApi);

        $this->success = 'Token API dibuat. Salin sekarang — token ini tidak bisa dilihat lagi.';
    }

    public function cabutTokenApi(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        Pengaturan::hapus(Pengaturan::API_TOKEN_HASH);
        $this->tokenBaru = null;
        unset($this->adaTokenApi);

        $this->success = 'Token API dicabut. API report kini tertutup.';
    }

    public function updatedHargaPublikDisembunyikan(bool $nilai): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, $nilai);

        $this->success = $nilai
            ? 'Harga kini disembunyikan dari pengunjung yang belum masuk.'
            : 'Harga kini terlihat untuk semua pengunjung.';
    }

    public function render()
    {
        return view('livewire.pengaturan-index');
    }
}
