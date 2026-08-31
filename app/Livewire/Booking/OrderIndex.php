<?php

namespace App\Livewire\Booking;

use App\Livewire\Concerns\WithSorting;
use App\Models\Cabang;
use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar order per cabang (CabangScope). Marketing hanya melihat order yang
 * di-assign ke dirinya; admin/area melihat semua order cabang. Filter: cari,
 * status pembayaran, status event, tahap milestone, rentang tanggal event, dan
 * (khusus admin) cabang lewat strip per-cabang.
 */
#[Layout('layouts.app')]
class OrderIndex extends Component
{
    use WithPagination, WithSorting;

    protected function sortableColumns(): array
    {
        return [
            'booking' => 'booking_code',
            'event' => 'tanggal_event',
            'total' => 'total',
            'status' => 'status',
        ];
    }

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';        // status pembayaran

    #[Url]
    public string $eventStatus = '';   // status event

    #[Url]
    public string $tahap = '';         // milestone: butuh_h7|butuh_h2|butuh_hh|terlewat

    #[Url]
    public string $dari = '';          // tanggal event dari

    #[Url]
    public string $sampai = '';        // tanggal event sampai

    #[Url]
    public string $cabangId = '';      // filter cabang (admin)

    #[Url]
    public bool $sampah = false;       // tampilkan order terhapus (super admin)

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    public function updated($name): void
    {
        if (in_array($name, ['q', 'status', 'eventStatus', 'tahap', 'dari', 'sampai', 'cabangId', 'sampah'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['q', 'status', 'eventStatus', 'tahap', 'dari', 'sampai', 'cabangId', 'sampah']);
        $this->resetPage();
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /** Pulihkan order dari sampah (super admin). */
    public function pulihkan(int $id): void
    {
        abort_unless($this->isSuperAdmin, 403);
        $order = Order::onlyTrashed()->findOrFail($id);
        $order->restore();
        $order->catat('order_dipulihkan');
    }

    /** Hapus permanen order dari sampah (super admin) + relasi anak. */
    public function hapusPermanen(int $id): void
    {
        abort_unless($this->isSuperAdmin, 403);
        $order = Order::onlyTrashed()->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->timEvent()->detach();
            $order->pembayaran()->delete();   // + cascade
            $order->activities()->delete();
            $order->forceDelete();
        });
    }

    /** Builder dengan SEMUA filter kecuali cabang (dipakai untuk daftar + hitung per-cabang). */
    private function filtered()
    {
        $user = auth()->user();
        $selesai = OrderStatus::EVENT_SELESAI;
        $belumSelesai = fn ($w) => $w->whereNull('event_status')->orWhere('event_status', '!=', $selesai);

        return Order::query()
            ->when($this->sampah && $user->hasRole('super_admin'), fn ($x) => $x->onlyTrashed())
            ->when($user->hasRole('marketing') && ! $user->seesAllCabang(),
                fn ($x) => $x->where('marketing_id', $user->id))
            ->when($this->status !== '', fn ($x) => $x->where('status', $this->status))
            ->when($this->eventStatus !== '', fn ($x) => $x->where('event_status', $this->eventStatus))
            ->when($this->dari !== '', fn ($x) => $x->whereDate('tanggal_event', '>=', $this->dari))
            ->when($this->sampai !== '', fn ($x) => $x->whereDate('tanggal_event', '<=', $this->sampai))
            ->when($this->tahap === 'butuh_h7', fn ($x) => $x->whereNull('konfirmasi_h7_at')
                ->whereNotNull('tanggal_event')->where($belumSelesai)
                ->whereDate('tanggal_event', '<=', Carbon::now()->addDays(7)))
            ->when($this->tahap === 'butuh_h2', fn ($x) => $x->whereNull('konfirmasi_h2_at')
                ->whereNotNull('tanggal_event')->where($belumSelesai)
                ->whereDate('tanggal_event', '<=', Carbon::now()->addDays(2)))
            ->when($this->tahap === 'butuh_hh', fn ($x) => $x->whereNull('konfirmasi_hh_at')
                ->whereNotNull('tanggal_event')->where($belumSelesai)
                ->whereDate('tanggal_event', '<=', Carbon::now()))
            ->when($this->tahap === 'terlewat', fn ($x) => $x->whereNotNull('tanggal_event')
                ->where($belumSelesai)
                ->whereDate('tanggal_event', '<', Carbon::now()))
            ->when(trim($this->q) !== '', function ($x) {
                $term = '%'.trim($this->q).'%';
                $x->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                    ->orWhereHas('sekolah', fn ($s) => $s->where('nama', 'ilike', $term)));
            });
    }

    public function render()
    {
        $isAdmin = auth()->user()->seesAllCabang();

        // Hitung per-cabang (admin) dari query terfilter — sebelum filter cabang.
        $counts = $isAdmin
            ? $this->filtered()->selectRaw('cabang_id, count(*) as c')->groupBy('cabang_id')->pluck('c', 'cabang_id')
            : collect();

        $ordersQuery = $this->filtered()
            ->when($this->cabangId !== '', fn ($x) => $x->where('cabang_id', $this->cabangId))
            ->with(['sekolah', 'cabang', 'marketing'])
            ->withCount('items')
            ->withCount(['pembayaran as pembayaran_pending_count' => fn ($q) => $q->where('status', \App\Models\OrderPembayaran::STATUS_PENDING)]);

        $orders = $this->applySort($ordersQuery, 'created_at', 'desc')
            ->orderBy('id') // tiebreaker → paginasi stabil saat sort kolom non-unik
            ->paginate(15); // CabangScope membatasi ke cabang staf

        return view('livewire.booking.order-index', [
            'orders' => $orders,
            'isAdmin' => $isAdmin,
            'cabangs' => $isAdmin ? Cabang::orderBy('nama')->get() : collect(),
            'cabangCounts' => $counts,
            'cabangTotal' => $counts->sum(),
            'statusOptions' => [
                '' => 'Semua pembayaran',
                'baru' => 'Menunggu DP',
                'dp' => 'DP dibayar',
                'lunas' => 'Lunas',
                'batal' => 'Batal',
            ],
            'eventStatusOptions' => [
                '' => 'Semua status event',
                OrderStatus::EVENT_DIJADWALKAN => 'Dijadwalkan',
                OrderStatus::EVENT_SELESAI => 'Selesai',
                OrderStatus::EVENT_BATAL => 'Batal',
            ],
            'tahapOptions' => [
                '' => 'Semua tahap',
                'butuh_h7' => 'Butuh konfirmasi H-7',
                'butuh_h2' => 'Butuh konfirmasi H-2',
                'butuh_hh' => 'Butuh konfirmasi Hari-H',
                'terlewat' => 'Terlewat (belum selesai)',
            ],
        ]);
    }
}
