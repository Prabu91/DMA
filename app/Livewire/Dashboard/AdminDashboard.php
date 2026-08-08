<?php

namespace App\Livewire\Dashboard;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard admin operasional (super_admin / operasional / area).
 * Fokus tindakan: apa yang perlu ditangani + event terdekat + tren & kinerja.
 * Filter cabang + rentang tanggal + toggle basis (order masuk / tgl event).
 * Area otomatis ter-scope cabangnya (CabangScope).
 */
class AdminDashboard extends Component
{
    #[Url]
    public string $cabangId = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    #[Url]
    public string $basis = 'booking'; // 'booking' | 'event'

    public function resetFilter(): void
    {
        $this->reset(['cabangId', 'dari', 'sampai']);
        $this->basis = 'booking';
    }

    private function isAdmin(): bool
    {
        return auth()->user()->seesAllCabang();
    }

    /** Belum selesai termasuk event_status NULL (SQL: NULL != x → false). */
    private function belumSelesai(): \Closure
    {
        $s = OrderStatus::EVENT_SELESAI;

        return fn ($w) => $w->whereNull('event_status')->orWhere('event_status', '!=', $s);
    }

    /** Builder terfilter (tanggal + cabang) untuk ringkasan/per-cabang/tren/kinerja. */
    private function filtered()
    {
        $col = $this->basis === 'event' ? 'tanggal_event' : 'tanggal_booking';

        return Order::query()
            ->when($this->dari !== '', fn ($q) => $q->whereDate($col, '>=', $this->dari))
            ->when($this->sampai !== '', fn ($q) => $q->whereDate($col, '<=', $this->sampai))
            ->when($this->cabangId !== '', fn ($q) => $q->where('cabang_id', $this->cabangId));
    }

    /** Builder "kondisi sekarang" (hanya cabang, abaikan rentang tanggal). */
    private function live()
    {
        return Order::query()->when($this->cabangId !== '', fn ($q) => $q->where('cabang_id', $this->cabangId));
    }

    public function render()
    {
        $today = Carbon::now();
        $lunas = OrderStatus::LUNAS;
        $belumSelesai = $this->belumSelesai();

        // ---- Perlu tindakan (live) ----
        $perluTindakan = [
            'belum_assign' => (clone $this->live())->where('sumber', 'sekolah')->whereNull('marketing_id')->count(),
            'terlewat' => (clone $this->live())->whereNotNull('tanggal_event')->where($belumSelesai)
                ->whereDate('tanggal_event', '<', $today)->count(),
            'menunggu_dp' => (clone $this->live())->where('status', OrderStatus::BARU)->count(),
            'event_minggu_ini' => (clone $this->live())->whereNotNull('tanggal_event')->where($belumSelesai)
                ->whereBetween('tanggal_event', [$today->copy()->startOfDay(), $today->copy()->addDays(7)->endOfDay()])->count(),
        ];

        // ---- Ringkasan (filtered) ----
        $summary = [
            'order' => (clone $this->filtered())->count(),
            'aktif' => (clone $this->filtered())->whereIn('status', OrderStatus::AKTIF)->count(),
            'lunas' => (clone $this->filtered())->where('status', $lunas)->count(),
        ];

        // ---- Per cabang (filtered) ----
        $cabangList = Cabang::query()
            ->when(! $this->isAdmin(), fn ($q) => $q->where('id', auth()->user()->cabang_id))
            ->when($this->cabangId !== '', fn ($q) => $q->where('id', $this->cabangId))
            ->orderBy('nama')->get();

        $perCabang = $cabangList->map(fn ($c) => [
            'cabang' => $c,
            'order' => (clone $this->filtered())->where('cabang_id', $c->id)->count(),
            'aktif' => (clone $this->filtered())->where('cabang_id', $c->id)->whereIn('status', OrderStatus::AKTIF)->count(),
            'lunas' => (clone $this->filtered())->where('cabang_id', $c->id)->where('status', $lunas)->count(),
        ]);

        // ---- Agenda event terdekat (live, dari hari ini) ----
        $agenda = (clone $this->live())
            ->whereNotNull('tanggal_event')->where($belumSelesai)
            ->whereDate('tanggal_event', '>=', $today)
            ->with(['sekolah', 'cabang', 'timEvent'])
            ->orderBy('tanggal_event')->limit(6)->get();

        // ---- Tren order masuk 8 minggu terakhir ----
        $sejak = $today->copy()->subWeeks(7)->startOfWeek();
        $trenOrders = (clone $this->live())
            ->whereNotNull('tanggal_booking')->where('tanggal_booking', '>=', $sejak)
            ->get(['tanggal_booking']);
        $tren = collect(range(0, 7))->map(function ($i) use ($sejak, $trenOrders) {
            $ws = $sejak->copy()->addWeeks($i);
            $we = $ws->copy()->endOfWeek();
            $count = $trenOrders->filter(fn ($o) => $o->tanggal_booking->betweenIncluded($ws, $we))->count();

            return ['label' => $ws->format('d/m'), 'count' => $count];
        });
        $trenMax = max(1, $tren->max('count'));

        // ---- Kinerja marketing (filtered) ----
        $topRaw = (clone $this->filtered())
            ->whereNotNull('marketing_id')
            ->selectRaw('marketing_id, count(*) as total, count(*) filter (where status = ?) as lunas', [$lunas])
            ->groupBy('marketing_id')->orderByDesc('total')->limit(5)->get();
        $users = User::whereIn('id', $topRaw->pluck('marketing_id'))->get()->keyBy('id');
        $kinerja = $topRaw->map(fn ($r) => [
            'nama' => optional($users->get($r->marketing_id))->nama ?? optional($users->get($r->marketing_id))->name ?? '—',
            'total' => (int) $r->total,
            'lunas' => (int) $r->lunas,
        ]);
        $kinerjaMax = max(1, $kinerja->max('total') ?? 1);

        // ---- Aktivitas terbaru (live, ter-scope cabang via relasi order) ----
        $aktivitas = OrderActivity::query()
            ->with(['user', 'order.sekolah', 'order.cabang'])
            ->whereHas('order', fn ($o) => $o->when($this->cabangId !== '' && $this->isAdmin(), fn ($x) => $x->where('cabang_id', $this->cabangId)))
            ->latest('created_at')->limit(8)->get();

        return view('livewire.dashboard.admin-dashboard', [
            'isAdmin' => $this->isAdmin(),
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'perluTindakan' => $perluTindakan,
            'summary' => $summary,
            'perCabang' => $perCabang,
            'agenda' => $agenda,
            'tren' => $tren,
            'trenMax' => $trenMax,
            'kinerja' => $kinerja,
            'kinerjaMax' => $kinerjaMax,
            'aktivitas' => $aktivitas,
        ]);
    }
}
