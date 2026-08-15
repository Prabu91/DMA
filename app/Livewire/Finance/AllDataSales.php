<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\WithSorting;
use App\Models\Cabang;
use App\Models\Order;
use App\Support\OrderStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Finance — All Data Sales. Semua order yang sudah ditugaskan marketing
 * (non-batal) + rincian finansial. Klik kategori → modal produk & breakdown.
 */
#[Layout('layouts.app')]
class AllDataSales extends Component
{
    use WithPagination, WithSorting;

    #[Url]
    public string $q = '';

    #[Url]
    public string $cabangId = '';

    #[Url]
    public string $statusBayar = '';

    #[Url]
    public string $grup = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    #[Url]
    public int $perPage = 25;

    public ?int $detailId = null; // modal

    protected function sortableColumns(): array
    {
        return ['booking' => 'booking_code', 'tanggal' => 'tanggal_booking', 'total' => 'total'];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin_sales']), 403);
    }

    public function updated($name): void
    {
        if (in_array($name, ['q', 'cabangId', 'statusBayar', 'grup', 'dari', 'sampai', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['q', 'cabangId', 'statusBayar', 'grup', 'dari', 'sampai']);
        $this->resetPage();
    }

    public function adaFilter(): bool
    {
        return $this->q !== '' || $this->cabangId !== '' || $this->statusBayar !== ''
            || $this->grup !== '' || $this->dari !== '' || $this->sampai !== '';
    }

    #[Computed]
    public function isLintasCabang(): bool
    {
        return auth()->user()->seesAllCabang();
    }

    public function lihatDetail(int $id): void
    {
        $this->detailId = $id;
    }

    public function tutupDetail(): void
    {
        $this->detailId = null;
    }

    #[Computed]
    public function detailOrder(): ?Order
    {
        return $this->detailId
            ? Order::with(['items.produk', 'items.paket', 'items.desain', 'sekolah', 'pembayaran'])->find($this->detailId)
            : null;
    }

    private function baseQuery()
    {
        return Order::query()
            ->whereNotNull('marketing_id')
            ->where('status', '!=', OrderStatus::BATAL)
            ->when($this->cabangId !== '' && $this->isLintasCabang, fn ($x) => $x->where('cabang_id', $this->cabangId))
            ->when($this->statusBayar !== '', fn ($x) => $x->where('status', $this->statusBayar))
            ->when($this->dari !== '', fn ($x) => $x->whereDate('tanggal_booking', '>=', $this->dari))
            ->when($this->sampai !== '', fn ($x) => $x->whereDate('tanggal_booking', '<=', $this->sampai))
            ->when($this->grup !== '', function ($x) {
                $x->where(function ($w) {
                    $w->whereHas('items.produk.kategori', fn ($k) => $k->where('grup', $this->grup))
                        ->orWhereHas('items.paket.produk.kategori', fn ($k) => $k->where('grup', $this->grup));
                });
            })
            ->when(trim($this->q) !== '', function ($x) {
                $t = '%'.trim($this->q).'%';
                $x->where(fn ($w) => $w->where('booking_code', 'ilike', $t)
                    ->orWhereHas('sekolah', fn ($s) => $s->where('nama', 'ilike', $t)->orWhere('id_sekolah', 'ilike', $t)));
            });
    }

    public function render()
    {
        $query = $this->baseQuery()
            ->with([
                'sekolah', 'marketing', 'pembayaran',
                'items.produk.kategori', 'items.paket.produk.kategori',
            ]);

        $orders = $this->applySort($query, 'tanggal_booking', 'desc')
            ->orderBy('id')
            ->paginate($this->perPage);

        return view('livewire.finance.all-data-sales', [
            'orders' => $orders,
            'cabangOptions' => $this->isLintasCabang ? Cabang::orderBy('nama')->pluck('nama', 'id')->all() : [],
            'grupOptions' => \App\Models\Kategori::GRUP,
        ]);
    }
}
