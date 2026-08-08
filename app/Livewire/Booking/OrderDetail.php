<?php

namespace App\Livewire\Booking;

use App\Models\Order;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrderDetail extends Component
{
    public string $konteks = 'staf';

    public int $orderId;

    // Edit jadwal event (konteks staf).
    public ?string $tanggalEvent = null;

    public ?string $jamEvent = null;

    public ?string $jadwalMsg = null;

    // Status pembayaran (konteks staf).
    public ?string $catatan = null;

    public ?string $statusMsg = null;

    public ?string $milestoneMsg = null;

    // Assign tim event (konteks staf).
    public array $timEventTerpilih = [];

    public ?string $timMsg = null;

    public function mount(string $konteks, int $orderId): void
    {
        $this->konteks = $konteks === 'sekolah' ? 'sekolah' : 'staf';
        $this->orderId = $orderId;

        if ($this->konteks === 'staf') {
            $this->tanggalEvent = optional($this->order->tanggal_event)->toDateString();
            $this->jamEvent = $this->order->jam_event;
            $this->catatan = $this->order->keterangan;
            $this->timEventTerpilih = $this->order->timEvent->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    /** Riwayat aktivitas order (dengan pelaku). */
    #[Computed]
    public function activities()
    {
        return $this->order->activities()->with('user')->limit(50)->get();
    }

    /** Anggota tim event di cabang order (untuk di-assign). */
    #[Computed]
    public function timEventOptions()
    {
        return User::role('tim_event')
            ->where('cabang_id', $this->order->cabang_id)
            ->orderBy('nama')
            ->get(['id', 'nama', 'name']);
    }

    /** Simpan penugasan tim event (sync pivot; hanya tim_event secabang). */
    public function simpanTimEvent(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $ids = array_map('intval', $this->timEventTerpilih);
        $valid = User::role('tim_event')
            ->where('cabang_id', $this->order->cabang_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $this->order->timEvent()->sync($valid);
        $nama = User::whereIn('id', $valid)->get()->map(fn ($u) => $u->nama ?? $u->name)->implode(', ');
        $this->order->catat('tim_event', $nama ?: 'dikosongkan');

        unset($this->order);
        $this->timMsg = 'Tim event diperbarui.';
    }

    /** Transisi status pembayaran yang diizinkan dari status sekarang. */
    private const TRANSISI = [
        'baru' => ['dp', 'lunas', 'batal'],
        'dp' => ['lunas', 'batal'],
        'lunas' => ['batal'],
        'batal' => ['baru'],
    ];

    /** Ubah status pembayaran (marketing/area/operasional/super_admin). */
    public function ubahStatus(string $to): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $current = $this->order->status ?: 'baru';
        abort_unless(in_array($to, self::TRANSISI[$current] ?? [], true), 422);

        $this->order->update([
            'status' => $to,
            'keterangan' => $this->catatan ?: null,
        ]);
        $this->order->catat('status_'.$to, $this->catatan ?: null);

        unset($this->order);
        $this->statusMsg = 'Status diperbarui: '.\App\Support\OrderStatus::label($to).'.';
    }

    /** Konfirmasi milestone event (h7|h2|hh) oleh staf. */
    public function konfirmasiMilestone(string $key): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $col = Order::MILESTONE_COL[$key] ?? null;
        abort_unless($col !== null, 422);
        abort_if($this->order->tanggal_event === null, 422);

        $this->order->update([$col => now()]);
        $this->order->catat('milestone_'.$key);

        unset($this->order);
        $this->milestoneMsg = 'Milestone '.strtoupper($key).' dikonfirmasi.';
    }

    /** Marketing/area/operasional/super_admin ubah tanggal & jam event. */
    public function simpanJadwal(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $this->validate([
            'tanggalEvent' => ['required', 'date'],
            'jamEvent' => ['nullable', 'string', 'max:20'],
        ], [
            'tanggalEvent.required' => 'Tanggal event wajib diisi.',
        ]);

        $this->order->update([
            'tanggal_event' => $this->tanggalEvent,
            'jam_event' => $this->jamEvent ?: null,
        ]);
        $this->order->catat('jadwal', $this->tanggalEvent.($this->jamEvent ? ' '.$this->jamEvent : ''));

        unset($this->order);
        $this->jadwalMsg = 'Jadwal event diperbarui.';
    }

    #[Computed]
    public function order(): Order
    {
        $with = ['items.produk', 'items.paket', 'items.desain', 'sekolah', 'cabang', 'marketing', 'timEvent'];

        if ($this->konteks === 'sekolah') {
            // Isolasi eksplisit: hanya order milik sekolah yang login.
            return Order::where('sekolah_id', auth('sekolah')->id())
                ->where('id', $this->orderId)
                ->with($with)
                ->firstOrFail();
        }

        // Staf: CabangScope membatasi ke cabang staf.
        return Order::with($with)->findOrFail($this->orderId);
    }

    #[Computed]
    public function paidItems()
    {
        return $this->order->items->where('is_free', false);
    }

    #[Computed]
    public function freeItems()
    {
        return $this->order->items->where('is_free', true);
    }

    public function kembaliUrl(): string
    {
        return $this->konteks === 'sekolah'
            ? route('sekolah.riwayat.index')
            : route('app.order.index');
    }

    public function pdfUrl(): string
    {
        return $this->konteks === 'sekolah'
            ? route('sekolah.riwayat.pdf', $this->orderId)
            : route('app.order.pdf', $this->orderId);
    }

    public function qrSvg(): ?string
    {
        return $this->order->booking_code
            ? \App\Support\Qr::svg($this->order->booking_code, 150)
            : null;
    }

    public function render()
    {
        return view('livewire.booking.order-detail');
    }
}
