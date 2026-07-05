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

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    #[Computed]
    public function isMarketing(): bool
    {
        return auth()->user()->hasRole('marketing');
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'operasional', 'area']);
    }

    #[Computed]
    public function orders()
    {
        $q = Order::query()
            ->where('sumber', 'sekolah')
            ->with(['sekolah', 'cabang'])
            ->withCount('items')
            ->latest();

        if ($this->tampil === 'ditugaskan') {
            $q->whereNotNull('marketing_id')->with('marketing');
        } else {
            $q->whereNull('marketing_id');
        }

        return $q->get(); // CabangScope membatasi ke cabang staf
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
        $this->success = 'Order berhasil diambil.';
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
