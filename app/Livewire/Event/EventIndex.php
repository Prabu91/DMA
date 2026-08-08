<?php

namespace App\Livewire\Event;

use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Jadwal Event untuk tim event: daftar event yang di-assign ke user ini
 * (via pivot order_tim_event). Admin lintas cabang (super_admin/operasional)
 * melihat semua event cabang sebagai pengawasan. Baris → detail event.
 */
#[Layout('layouts.app')]
class EventIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        // Tim event: hanya event yang ditugaskan. Admin: semua (ter-scope cabang).
        $query = $user->hasRole('tim_event') && ! $user->seesAllCabang()
            ? $user->ordersAsTimEvent()->getQuery()
            : Order::query();

        $events = $query
            ->with(['sekolah', 'cabang'])
            ->whereNotNull('marketing_id') // hanya order yang sudah di-assign marketing
            ->whereNotNull('tanggal_event')
            ->when($this->status !== '', fn ($x) => $x->where('event_status', $this->status))
            ->orderBy('tanggal_event')
            ->paginate(15);

        return view('livewire.event.event-index', [
            'events' => $events,
            'statusOptions' => [
                '' => 'Semua status',
                OrderStatus::EVENT_DIJADWALKAN => 'Dijadwalkan',
                OrderStatus::EVENT_SELESAI => 'Selesai',
                OrderStatus::EVENT_BATAL => 'Batal',
            ],
        ]);
    }
}
