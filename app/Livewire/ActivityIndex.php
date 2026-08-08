<?php

namespace App\Livewire;

use App\Models\Cabang;
use App\Models\OrderActivity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aktivitas lintas order (audit). Admin lihat semua; area ter-scope cabangnya
 * (via CabangScope pada relasi order). Filter: cari kode/sekolah, jenis aksi,
 * rentang tanggal, dan (admin) cabang.
 */
#[Layout('layouts.app')]
class ActivityIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    #[Url]
    public string $cabangId = '';

    public function updated($name): void
    {
        if (in_array($name, ['q', 'action', 'dari', 'sampai', 'cabangId'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['q', 'action', 'dari', 'sampai', 'cabangId']);
        $this->resetPage();
    }

    public function render()
    {
        $isAdmin = auth()->user()->seesAllCabang();

        $activities = OrderActivity::query()
            ->with(['user', 'order.sekolah', 'order.cabang'])
            // whereHas('order') menerapkan CabangScope → area ter-scope otomatis.
            ->whereHas('order', function ($o) use ($isAdmin) {
                $o->when($isAdmin && $this->cabangId !== '', fn ($x) => $x->where('cabang_id', $this->cabangId))
                    ->when(trim($this->q) !== '', function ($x) {
                        $term = '%'.trim($this->q).'%';
                        $x->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                            ->orWhereHas('sekolah', fn ($s) => $s->where('nama', 'ilike', $term)));
                    });
            })
            ->when($this->action !== '', fn ($x) => $x->where('action', $this->action))
            ->when($this->dari !== '', fn ($x) => $x->whereDate('created_at', '>=', $this->dari))
            ->when($this->sampai !== '', fn ($x) => $x->whereDate('created_at', '<=', $this->sampai))
            ->latest('created_at')
            ->paginate(30);

        return view('livewire.activity-index', [
            'activities' => $activities,
            'isAdmin' => $isAdmin,
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'actionOptions' => ['' => 'Semua aksi'] + OrderActivity::LABELS,
        ]);
    }
}
