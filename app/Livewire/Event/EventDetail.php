<?php

namespace App\Livewire\Event;

use App\Mail\EventOtpMail;
use App\Models\Desain;
use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail pelaksanaan event untuk tim event (berbasis STE).
 * TE1: tampil detail. TE2: konfirmasi ulang detail di lokasi + revisi
 * (edit nama/alamat sekolah + desain/kode item). OTP penyelesaian: TE3.
 * Otorisasi: manageEvent (tim event ter-assign, atau admin lintas cabang).
 */
#[Layout('layouts.app')]
class EventDetail extends Component
{
    public int $orderId;

    // Revisi (TE2)
    public bool $revisiMode = false;

    public string $namaSekolah = '';

    public ?string $alamatSekolah = null;

    public ?string $kotaSekolah = null;

    /** @var array<int, int|string|null> [order_item_id => desain_id] */
    public array $itemDesain = [];

    // Penyelesaian OTP (TE3)
    public string $otpInput = '';

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->authorize('manageEvent', $this->order());
    }

    #[Computed]
    public function order(): Order
    {
        return Order::with([
            'sekolah', 'cabang', 'marketing',
            'items.produk.kategori', 'items.paket', 'items.desain',
        ])->findOrFail($this->orderId);
    }

    /**
     * Opsi desain per item order yang berdesain (kategori pakai_desain).
     * [order_item_id => [desain_id => 'KODE — seri']]
     *
     * @return array<int, array<int, string>>
     */
    #[Computed]
    public function desainOptionsPerItem(): array
    {
        $out = [];
        foreach ($this->order()->items as $item) {
            if ($item->tipe_item !== 'produk' || ! $item->produk?->kategori?->pakai_desain) {
                continue;
            }
            $out[$item->id] = Desain::where('kategori_id', $item->produk->kategori_id)
                ->where('status', 'aktif')
                ->orderBy('kode')
                ->get()
                ->mapWithKeys(fn ($d) => [$d->id => $d->kode.($d->seri ? ' — '.$d->seri : '')])
                ->all();
        }

        return $out;
    }

    public function konfirmasiLokasi(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        $order->update(['konfirmasi_lokasi_at' => now()]);
        $order->catat('konfirmasi_lokasi');
        unset($this->order); // segarkan computed
        session()->flash('event-flash', 'Detail dikonfirmasi sesuai di lokasi.');
    }

    public function mulaiRevisi(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        $this->resetErrorBag();
        $this->namaSekolah = $order->sekolah?->nama ?? '';
        $this->alamatSekolah = $order->sekolah?->alamat;
        $this->kotaSekolah = $order->sekolah?->kota;
        $this->itemDesain = [];
        foreach (array_keys($this->desainOptionsPerItem()) as $itemId) {
            $item = $order->items->firstWhere('id', $itemId);
            $this->itemDesain[$itemId] = $item?->desain_id;
        }
        $this->revisiMode = true;
    }

    public function batalRevisi(): void
    {
        $this->revisiMode = false;
        $this->resetErrorBag();
    }

    public function simpanRevisi(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        $this->validate([
            'namaSekolah' => ['required', 'string', 'max:255'],
            'alamatSekolah' => ['nullable', 'string', 'max:500'],
            'kotaSekolah' => ['nullable', 'string', 'max:100'],
        ]);

        // Validasi desain: tiap pilihan harus opsi sah untuk item tsb.
        $opsi = $this->desainOptionsPerItem();
        foreach ($this->itemDesain as $itemId => $desainId) {
            if ($desainId === null || $desainId === '') {
                continue;
            }
            if (! isset($opsi[$itemId]) || ! array_key_exists((int) $desainId, $opsi[$itemId])) {
                $this->addError('itemDesain.'.$itemId, 'Desain tidak valid untuk item ini.');

                return;
            }
        }

        // Update data sekolah.
        if ($order->sekolah) {
            $order->sekolah->update([
                'nama' => $this->namaSekolah,
                'alamat' => $this->alamatSekolah,
                'kota' => $this->kotaSekolah,
            ]);
        }

        // Update desain/kode tiap item berdesain.
        foreach ($this->itemDesain as $itemId => $desainId) {
            if (! isset($opsi[$itemId])) {
                continue;
            }
            $item = $order->items->firstWhere('id', (int) $itemId);
            $item?->update(['desain_id' => $desainId !== '' ? $desainId : null]);
        }

        $order->catat('revisi', 'sekolah & desain item');
        $this->revisiMode = false;
        unset($this->order, $this->desainOptionsPerItem);
        session()->flash('event-flash', 'Revisi detail order tersimpan.');
    }

    /**
     * Generate OTP penyelesaian & kirim ke guru (portal sekolah + email).
     * Hanya boleh setelah detail dikonfirmasi di lokasi. Kode TIDAK
     * ditampilkan ke tim event — mereka mengetik ulang dari guru.
     */
    public function generateOtp(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        if (! $order->konfirmasi_lokasi_at) {
            $this->addError('otpInput', 'Konfirmasi detail di lokasi dulu sebelum membuat OTP.');

            return;
        }

        // Cooldown kirim-ulang (hanya berlaku bila OTP sudah pernah dibuat).
        if (($sisa = $order->otpResendSecondsLeft()) > 0) {
            $this->addError('otpInput', "Tunggu {$sisa} detik sebelum mengirim ulang OTP.");

            return;
        }

        $code = $order->generateEventOtp();
        $order->catat('otp_dibuat');

        // Kirim ke email login akun (guru); fallback email_guru utk sekolah buatan staf.
        $email = $order->sekolah?->email ?: $order->sekolah?->email_guru;
        if ($email) {
            Mail::to($email)->send(new EventOtpMail($order->fresh(), $code));
            session()->flash('event-flash', 'OTP dibuat & dikirim ke guru ('.$email.') serta tampil di portal sekolah.');
        } else {
            session()->flash('event-flash', 'OTP dibuat & tampil di portal sekolah (email guru belum diisi).');
        }

        unset($this->order);
        $this->otpInput = '';
    }

    /** Tim event input OTP dari guru → validasi → event selesai. */
    public function selesaikanDenganOtp(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        $this->validate(
            ['otpInput' => ['required', 'digits:6']],
            ['otpInput.required' => 'Masukkan kode OTP dari guru.', 'otpInput.digits' => 'OTP harus 6 digit.']
        );

        if (! $order->eventOtpMatches($this->otpInput)) {
            $this->addError('otpInput', 'OTP salah atau sudah kedaluwarsa.');

            return;
        }

        $order->update([
            'event_status' => OrderStatus::EVENT_SELESAI,
            'event_selesai_at' => now(),
            'otp_code' => null,
            'otp_expires' => null,
        ]);
        $order->catat('event_selesai', 'via OTP');

        unset($this->order);
        $this->otpInput = '';
        session()->flash('event-flash', 'Event selesai. Terima kasih!');
    }

    /**
     * Override admin (super_admin/operasional): selesaikan tanpa OTP —
     * mis. guru tidak di tempat. Hanya untuk yang melihat lintas cabang.
     */
    public function selesaikanOverride(): void
    {
        $order = $this->order();
        abort_unless(Auth::user()->seesAllCabang(), 403);
        abort_if($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        $order->update([
            'event_status' => OrderStatus::EVENT_SELESAI,
            'event_selesai_at' => now(),
            'otp_code' => null,
            'otp_expires' => null,
        ]);
        $order->catat('event_selesai', 'override admin');

        unset($this->order);
        session()->flash('event-flash', 'Event diselesaikan (override admin, tanpa OTP).');
    }

    #[Computed]
    public function activities()
    {
        return $this->order()->activities()->with('user')->limit(50)->get();
    }

    public function render()
    {
        return view('livewire.event.event-detail');
    }
}
