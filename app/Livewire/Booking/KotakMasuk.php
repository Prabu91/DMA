<?php

namespace App\Livewire\Booking;

use App\Models\Order;
use App\Models\User;
use App\Services\CodeGenerator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kotak masuk order jalur sekolah (sumber='sekolah') per cabang.
 * - Marketing: "Ambil" (klaim atomik ke diri sendiri).
 * - Area/operasional/super_admin: assign/reassign ke marketing tertentu.
 */
#[Layout('layouts.app')]
class KotakMasuk extends Component
{
    public string $tampil = 'baru'; // 'baru' (belum ditugaskan) | 'ditugaskan'

    public array $pilihMarketing = []; // orderId => user_id

    public string $q = '';

    public string $dari = '';   // tanggal masuk dari

    public string $sampai = ''; // tanggal masuk sampai

    public string $cabangId = ''; // filter cabang (admin)

    public ?int $detailId = null; // order yang detailnya dibuka (modal)

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    /** Order yang detailnya sedang dilihat (modal preview). */
    #[Computed]
    public function detailOrder(): ?Order
    {
        if (! $this->detailId) {
            return null;
        }

        return Order::with(['sekolah.kecamatan', 'cabang', 'marketing', 'items.produk', 'items.paket', 'items.desain'])
            ->find($this->detailId); // ter-scope cabang
    }

    public function lihatDetail(int $orderId): void
    {
        $this->detailId = $orderId;
    }

    public function tutupDetail(): void
    {
        $this->detailId = null;
    }

    #[Computed]
    public function isMarketing(): bool
    {
        return auth()->user()->hasRole('marketing');
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operasional', 'admin_sales']);
    }

    #[Computed]
    public function orders()
    {
        return $this->baseQuery()
            ->when($this->cabangId !== '', fn ($x) => $x->where('cabang_id', $this->cabangId))
            ->with(['sekolah.kecamatan', 'cabang', 'marketing'])
            ->withCount('items')
            ->latest()
            ->get(); // CabangScope membatasi ke cabang staf
    }

    /** Query dasar (tanpa filter cabang) — untuk daftar & hitung per-cabang. */
    private function baseQuery()
    {
        $q = Order::query()
            ->where('sumber', 'sekolah')
            ->when($this->dari !== '', fn ($x) => $x->whereDate('tanggal_booking', '>=', $this->dari))
            ->when($this->sampai !== '', fn ($x) => $x->whereDate('tanggal_booking', '<=', $this->sampai))
            ->when(trim($this->q) !== '', function ($x) {
                $term = '%'.trim($this->q).'%';
                $x->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                    ->orWhereHas('sekolah', fn ($s) => $s->where('nama', 'ilike', $term)));
            });

        $this->tampil === 'ditugaskan'
            ? $q->whereNotNull('marketing_id')
            : $q->whereNull('marketing_id');

        return $q;
    }

    /** Hitung order per cabang (admin) sesuai filter/tab aktif. */
    #[Computed]
    public function cabangCounts()
    {
        return $this->isAdmin
            ? $this->baseQuery()->selectRaw('cabang_id, count(*) as c')->groupBy('cabang_id')->pluck('c', 'cabang_id')
            : collect();
    }

    #[Computed]
    public function cabangList()
    {
        return $this->isAdmin ? \App\Models\Cabang::orderBy('nama')->get() : collect();
    }

    public function resetFilter(): void
    {
        $this->reset(['q', 'dari', 'sampai', 'cabangId']);
    }

    /** Marketing per cabang: [cabang_id => [user_id => nama]]. */
    #[Computed]
    public function marketingByCabang(): array
    {
        return User::role('marketing')->get(['id', 'nama', 'name', 'cabang_id'])
            ->groupBy('cabang_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($u) => [$u->id => $u->nama ?? $u->name])->all())
            ->all();
    }

    /** Klaim atomik oleh marketing (WHERE marketing_id IS NULL). */
    public function ambil(int $orderId): void
    {
        abort_unless($this->isMarketing(), 403);
        $this->success = $this->error = null;

        $affected = Order::whereKey($orderId)
            ->where('sumber', 'sekolah')
            ->whereNull('marketing_id')
            ->update(['marketing_id' => auth()->id()]);

        if ($affected === 0) {
            $this->error = 'Order sudah diambil marketing lain atau tidak tersedia.';

            return;
        }

        $this->buatKode($orderId);
        Order::find($orderId)?->catat('marketing_diambil');
        $this->success = 'Order berhasil diambil.';
        $this->detailId = null;
        unset($this->orders);
    }

    /** Assign oleh admin (order belum ditugaskan). */
    public function tugaskan(int $orderId): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->success = $this->error = null;

        $marketingId = $this->pilihMarketing[$orderId] ?? null;
        if (! $this->marketingValidUntuk($orderId, $marketingId)) {
            $this->error = 'Pilih marketing yang sesuai cabang terlebih dahulu.';

            return;
        }

        $affected = Order::whereKey($orderId)
            ->where('sumber', 'sekolah')
            ->whereNull('marketing_id')
            ->update(['marketing_id' => $marketingId]);

        if ($affected) {
            $this->buatKode($orderId);
            $nama = User::find($marketingId)?->nama ?? User::find($marketingId)?->name;
            Order::find($orderId)?->catat('marketing_ditugaskan', $nama ? 'ke '.$nama : null);
        }
        $this->success = $affected ? 'Order ditugaskan.' : 'Order sudah ditugaskan sebelumnya.';
        unset($this->orders);
    }

    /** Reassign oleh admin (order sudah ditugaskan). */
    public function reassign(int $orderId): void
    {
        abort_unless($this->isAdmin(), 403);
        $this->success = $this->error = null;

        $marketingId = $this->pilihMarketing[$orderId] ?? null;
        if (! $this->marketingValidUntuk($orderId, $marketingId)) {
            $this->error = 'Pilih marketing yang sesuai cabang terlebih dahulu.';

            return;
        }

        Order::whereKey($orderId)
            ->where('sumber', 'sekolah')
            ->update(['marketing_id' => $marketingId]);

        $nama = User::find($marketingId)?->nama ?? User::find($marketingId)?->name;
        Order::find($orderId)?->catat('marketing_ditugaskan', $nama ? 'ke '.$nama : null);
        $this->success = 'Penugasan diperbarui.';
        unset($this->orders);
    }

    /** Generate booking_code untuk order yang baru mendapat marketing. */
    private function buatKode(int $orderId): void
    {
        $order = Order::find($orderId); // ter-scope cabang
        if ($order && $order->marketing_id && ! $order->booking_code) {
            app(CodeGenerator::class)->generate($order);
        }
    }

    private function marketingValidUntuk(int $orderId, $marketingId): bool
    {
        if (! $marketingId) {
            return false;
        }
        $order = Order::find($orderId); // ter-scope cabang
        $marketing = User::role('marketing')->find($marketingId);

        return $order && $marketing && $marketing->cabang_id === $order->cabang_id;
    }

    public function render()
    {
        return view('livewire.booking.kotak-masuk');
    }
}
