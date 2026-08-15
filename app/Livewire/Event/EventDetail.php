<?php

namespace App\Livewire\Event;

use App\Models\Desain;
use App\Models\Order;
use App\Models\Paket;
use App\Models\Produk;
use App\Services\BookingService;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detail pelaksanaan event untuk tim event (berbasis STE).
 * TE1: tampil detail. TE2: konfirmasi ulang detail di lokasi + revisi
 * (edit data sekolah + desain + tambah/kurang item & jumlah). Konfirmasi
 * Hari-H = FINAL → order terkunci. OTP penyelesaian: TE3. Sampai kantor: TE4.
 * Otorisasi: manageEvent (tim event ter-assign, atau admin lintas cabang).
 */
#[Layout('layouts.app')]
class EventDetail extends Component
{
    use WithFileUploads;

    public int $orderId;

    // Bukti bayar DP
    public $buktiDp = null;

    // Catat pembayaran (tim event di lokasi)
    public string $bayarJenis = 'dp';

    public ?int $bayarJumlah = null;

    public ?string $bayarTanggal = null;

    public $bayarBukti = null;

    // Revisi (TE2)
    public bool $revisiMode = false;

    public string $namaSekolah = '';

    public ?string $alamatSekolah = null;

    public ?string $kotaSekolah = null;

    public ?string $picSekolah = null;

    public ?string $noTelpSekolah = null;

    /** @var array<int, int|string|null> [order_item_id => desain_id] */
    public array $itemDesain = [];

    // Tambah item (editor tim event)
    public string $tambahTipe = 'produk'; // 'produk' | 'paket'

    public ?int $tambahProdukId = null;

    public ?int $tambahPaketId = null;

    public ?string $tambahOpsi = null;

    public ?int $tambahDesainId = null;

    public int $tambahQty = 1;

    // Penyelesaian OTP (TE3)
    public string $otpInput = '';

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->authorize('manageEvent', $this->order());
        // Default tanggal bayar = tanggal event (DP di hari-H).
        $this->bayarTanggal = optional($this->order()->tanggal_event)->toDateString() ?? now()->toDateString();
    }

    /** Catat pembayaran (DP/pelunasan) oleh tim event di lokasi. */
    public function catatPembayaran(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->status === OrderStatus::BATAL, 422);

        $this->validate([
            'bayarJenis' => ['required', 'in:dp,pelunasan'],
            'bayarJumlah' => ['required', 'integer', 'min:1'],
            'bayarTanggal' => ['required', 'date'],
            'bayarBukti' => ['nullable', 'image', 'max:4096'],
        ], [
            'bayarJumlah.required' => 'Nominal wajib diisi.',
            'bayarJumlah.integer' => 'Nominal harus angka.',
        ]);

        $path = $this->bayarBukti ? $this->bayarBukti->store('bukti-bayar', 'public') : null;
        $order->pembayaran()->create([
            'jenis' => $this->bayarJenis,
            'jumlah' => $this->bayarJumlah,
            'tanggal_bayar' => $this->bayarTanggal,
            'dicatat_oleh' => auth('web')->id(),
            'bukti_path' => $path,
        ]);
        $order->load('pembayaran')->recalcStatusPembayaran();
        $order->catat('pembayaran_'.$this->bayarJenis, 'Rp'.number_format($this->bayarJumlah, 0, ',', '.'));

        $this->reset(['bayarJumlah', 'bayarBukti']);
        unset($this->order);
        session()->flash('event-flash', 'Pembayaran dicatat.');
    }

    #[Computed]
    public function order(): Order
    {
        return Order::with([
            'sekolah', 'cabang', 'marketing', 'pembayaran',
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
        abort_if($order->isLocked(), 422);

        $order->update(['konfirmasi_lokasi_at' => now()]);
        $order->catat('konfirmasi_lokasi');
        unset($this->order); // segarkan computed
        session()->flash('event-flash', 'Detail dikonfirmasi sesuai di lokasi.');
    }

    public function mulaiRevisi(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->isLocked(), 422);

        $this->resetErrorBag();
        $this->namaSekolah = $order->sekolah?->nama ?? '';
        $this->alamatSekolah = $order->sekolah?->alamat;
        $this->kotaSekolah = $order->sekolah?->kota;
        $this->picSekolah = $order->sekolah?->pic_sekolah;
        $this->noTelpSekolah = $order->sekolah?->no_telp_pic;
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
        abort_if($order->isLocked(), 422);

        $this->validate([
            'namaSekolah' => ['required', 'string', 'max:255'],
            'alamatSekolah' => ['nullable', 'string', 'max:500'],
            'kotaSekolah' => ['nullable', 'string', 'max:100'],
            'picSekolah' => ['nullable', 'string', 'max:255'],
            'noTelpSekolah' => ['nullable', 'string', 'max:30'],
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

        // Cegah duplikat sekolah (kombinasi nama+PIC+telp+alamat) — lintas cabang.
        if ($order->sekolah && \App\Models\Sekolah::comboExists([
            'nama' => $this->namaSekolah,
            'pic_sekolah' => $this->picSekolah,
            'no_telp_pic' => $this->noTelpSekolah,
            'alamat' => $this->alamatSekolah,
        ], $order->sekolah->id)) {
            $this->addError('namaSekolah', 'Data sekolah (nama, PIC, no. telp, alamat) sudah terdaftar.');

            return;
        }

        // Update data sekolah.
        if ($order->sekolah) {
            $order->sekolah->update([
                'nama' => $this->namaSekolah,
                'alamat' => $this->alamatSekolah,
                'kota' => $this->kotaSekolah,
                'pic_sekolah' => $this->picSekolah,
                'no_telp_pic' => $this->noTelpSekolah,
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

    // ---------- Editor item (tambah/kurang produk & jumlah) ----------

    /** Produk aktif untuk ditambahkan. */
    #[Computed]
    public function produkOptions()
    {
        return Produk::where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']);
    }

    /** Paket untuk ditambahkan. */
    #[Computed]
    public function paketOptions()
    {
        return Paket::orderBy('nama')->get(['id', 'nama']);
    }

    /** Opsi ukuran produk yang dipilih untuk ditambahkan. */
    #[Computed]
    public function opsiTambah(): array
    {
        if (! $this->tambahProdukId) {
            return [];
        }

        return Produk::with('opsi')->find($this->tambahProdukId)?->opsi
            ->mapWithKeys(fn ($o) => [$o->nilai_opsi => $o->nilai_opsi])->all() ?? [];
    }

    /** Opsi desain produk yang dipilih (bila kategori pakai desain). */
    #[Computed]
    public function desainTambah(): array
    {
        if (! $this->tambahProdukId) {
            return [];
        }
        $produk = Produk::with('kategori')->find($this->tambahProdukId);
        if (! $produk?->kategori?->pakai_desain) {
            return [];
        }

        return Desain::where('kategori_id', $produk->kategori_id)
            ->where('status', 'aktif')->orderBy('kode')->get()
            ->mapWithKeys(fn ($d) => [$d->id => $d->kode.($d->seri ? ' — '.$d->seri : '')])->all();
    }

    public function updatedTambahProdukId(): void
    {
        $this->tambahOpsi = null;
        $this->tambahDesainId = null;
        unset($this->opsiTambah, $this->desainTambah);
    }

    /** Tambah satu item (produk/paket) ke order lalu hitung ulang total & free. */
    public function tambahItem(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->isLocked(), 422);

        $this->validate([
            'tambahTipe' => ['required', 'in:produk,paket'],
            'tambahQty' => ['required', 'integer', 'min:1'],
            'tambahProdukId' => ['nullable', 'required_if:tambahTipe,produk', 'integer', 'exists:produk,id'],
            'tambahPaketId' => ['nullable', 'required_if:tambahTipe,paket', 'integer', 'exists:paket,id'],
        ], [], [
            'tambahProdukId' => 'produk',
            'tambahPaketId' => 'paket',
            'tambahQty' => 'jumlah',
        ]);

        if ($this->tambahTipe === 'paket') {
            $paket = Paket::findOrFail($this->tambahPaketId);
            $order->items()->create([
                'tipe_item' => 'paket',
                'paket_id' => $paket->id,
                'produk_id' => null,
                'desain_id' => null,
                'opsi_ukuran' => null,
                'qty' => $this->tambahQty,
                'harga' => (int) $paket->harga,
                'is_free' => false,
            ]);
            $nama = $paket->nama;
        } else {
            $produk = Produk::with('opsi')->findOrFail($this->tambahProdukId);
            $opsi = $this->tambahOpsi && array_key_exists($this->tambahOpsi, $this->opsiTambah) ? $this->tambahOpsi : null;
            $desainId = $this->tambahDesainId && array_key_exists((int) $this->tambahDesainId, $this->desainTambah) ? (int) $this->tambahDesainId : null;
            $order->items()->create([
                'tipe_item' => 'produk',
                'produk_id' => $produk->id,
                'paket_id' => null,
                'desain_id' => $desainId,
                'opsi_ukuran' => $opsi,
                'qty' => $this->tambahQty,
                'harga' => app(BookingService::class)->hargaProduk($produk, $opsi),
                'is_free' => false,
            ]);
            $nama = $produk->nama;
        }

        app(BookingService::class)->rebuildOrder($order);
        $order->catat('item_tambah', $nama.' ×'.$this->tambahQty);

        $this->reset(['tambahProdukId', 'tambahPaketId', 'tambahOpsi', 'tambahDesainId', 'tambahQty']);
        $this->tambahQty = 1;
        unset($this->order, $this->desainOptionsPerItem, $this->opsiTambah, $this->desainTambah);
        session()->flash('event-flash', 'Item ditambahkan & total dihitung ulang.');
    }

    /** Ubah jumlah item berbayar lalu hitung ulang. */
    public function ubahQtyItem(int $itemId, int $qty): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->isLocked(), 422);

        $item = $order->items()->where('id', $itemId)->where('is_free', false)->first();
        abort_unless($item, 404);

        $item->update(['qty' => max(1, $qty)]);
        app(BookingService::class)->rebuildOrder($order);
        $order->catat('item_qty', ($item->produk?->nama ?? $item->paket?->nama).' → '.max(1, $qty));

        unset($this->order, $this->desainOptionsPerItem);
        session()->flash('event-flash', 'Jumlah item diperbarui.');
    }

    /** Hapus item berbayar lalu hitung ulang. */
    public function hapusItem(int $itemId): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->isLocked(), 422);

        $item = $order->items()->where('id', $itemId)->where('is_free', false)->first();
        abort_unless($item, 404);

        $nama = $item->produk?->nama ?? $item->paket?->nama;
        $item->delete();
        app(BookingService::class)->rebuildOrder($order);
        $order->catat('item_hapus', $nama);

        unset($this->order, $this->desainOptionsPerItem);
        session()->flash('event-flash', 'Item dihapus & total dihitung ulang.');
    }

    /**
     * Konfirmasi HARI-H (final) oleh tim event → order TERKUNCI.
     * Sekaligus menandai konfirmasi lokasi bila belum, agar OTP bisa lanjut.
     */
    public function konfirmasiHariH(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_if($order->isLocked(), 422);
        abort_if($order->tanggal_event === null, 422);

        $order->update([
            'konfirmasi_hh_at' => now(),
            'konfirmasi_lokasi_at' => $order->konfirmasi_lokasi_at ?? now(),
        ]);
        $order->catat('milestone_hh', 'oleh tim event (final, order dikunci)');

        $this->revisiMode = false;
        unset($this->order);
        session()->flash('event-flash', 'Hari-H dikonfirmasi. Order final & terkunci. Lanjut ke penyelesaian (OTP).');
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

        // Sementara: OTP TIDAK dikirim email — cukup tampil di akun sekolah (portal).
        // Guru membacakan kode ke tim event. (Rencana lanjut: kirim via WA.)
        session()->flash('event-flash', 'OTP dibuat & tampil di akun sekolah. Minta guru membacakan kodenya.');

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

    /** Upload/ganti foto bukti bayar DP (tim event ter-assign). */
    public function uploadBuktiDp(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);

        $this->validate(
            ['buktiDp' => ['required', 'image', 'max:4096']],
            ['buktiDp.image' => 'File harus berupa gambar.', 'buktiDp.max' => 'Maksimal 4 MB.'],
        );

        $order->gantiBuktiDp($this->buktiDp);

        $this->reset('buktiDp');
        unset($this->order);
        session()->flash('event-flash', 'Bukti DP tersimpan.');
    }

    /**
     * Tim event klik "Sampai kantor" setelah event selesai (pasca-OTP) →
     * catat waktu tiba kembali di kantor.
     */
    public function sampaiKantor(): void
    {
        $order = $this->order();
        $this->authorize('manageEvent', $order);
        abort_unless($order->event_status === OrderStatus::EVENT_SELESAI, 422);

        if ($order->sampai_kantor_at) {
            return; // sudah tercatat
        }

        $order->update(['sampai_kantor_at' => now()]);
        $order->catat('sampai_kantor');

        unset($this->order);
        session()->flash('event-flash', 'Waktu sampai kantor tercatat. Terima kasih!');
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
