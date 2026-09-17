<?php

namespace App\Livewire\Booking;

use App\Models\Order;
use App\Models\OrderPembayaran;
use App\Models\User;
use App\Support\Cart;
use App\Support\FolderKerja;
use App\Support\OrderStatus;
use App\Support\Qr;
use App\Support\Redaksi;
use App\Support\WaPesan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrderDetail extends Component
{
    use WithFileUploads;

    public string $konteks = 'staf';

    public int $orderId;

    // Edit jadwal event (konteks staf).
    public ?string $tanggalEvent = null;

    public ?string $jamEvent = null;

    public ?string $jadwalMsg = null;

    // Status order (batal/aktifkan).
    public ?string $statusMsg = null;

    public ?string $milestoneMsg = null;

    // Catat pembayaran (konteks staf).
    public string $bayarJenis = 'dp';

    public ?int $bayarJumlah = null;

    public ?string $bayarTanggal = null;

    public $bayarBukti = null;

    // Edit pembayaran (admin sales).
    public ?int $editPembayaranId = null;

    public string $editJenis = 'dp';

    public ?int $editJumlah = null;

    public ?string $editTanggal = null;

    public $editBukti = null;

    public ?string $bayarMsg = null;

    // Ubah harga satuan item order (admin sales/super admin, tembus kunci).
    public ?int $hargaEditItemId = null;

    public ?int $hargaBaru = null;

    public ?string $hargaMsg = null;

    // Redaksi (teks cetak) — koreksi per order.
    public bool $redaksiEdit = false;

    public string $redaksiTeks = '';

    public ?string $redaksiMsg = null;

    // Diskon per item (ajukan/setujui). [order_item_id => nominal per satuan]
    public array $diskonItem = [];

    public ?string $diskonMsg = null;

    // Bukti bayar DP (konteks staf).
    public $buktiDp = null;

    public ?string $buktiMsg = null;

    // Catatan staf pada order (utas).
    public string $catatanBaru = '';

    public ?string $catatanMsg = null;

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
            $this->bayarTanggal = now()->toDateString();
            foreach ($this->order->items->where('is_free', false) as $it) {
                $this->diskonItem[$it->id] = (int) ($it->diskon_diajukan ?? $it->diskon);
            }
            $this->timEventTerpilih = $this->order->timEvent->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    /** Riwayat aktivitas order (dengan pelaku). */
    #[Computed]
    public function activities()
    {
        return $this->order->activities()->with('user')->limit(50)->get();
    }

    /** Catatan staf pada order ini, terlama di atas. */
    #[Computed]
    public function catatan()
    {
        return $this->order->catatan()->with('penulis:id,nama,name')->get();
    }

    /**
     * Tambah catatan. Sengaja TIDAK ikut terkunci saat order dikunci —
     * penguncian melindungi data order, sementara catatan justru paling
     * dibutuhkan saat ada persoalan di akhir.
     */
    public function tambahCatatan(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('view', $this->order);

        $this->validate(
            ['catatanBaru' => ['required', 'string', 'max:2000']],
            [
                'catatanBaru.required' => 'Catatan tidak boleh kosong.',
                'catatanBaru.max' => 'Catatan maksimal 2000 karakter.',
            ],
        );

        $this->order->catatan()->create([
            'user_id' => auth('web')->id(),
            'isi' => trim($this->catatanBaru),
        ]);

        $this->catatanBaru = '';
        unset($this->catatan);
        $this->catatanMsg = 'Catatan ditambahkan.';
    }

    /**
     * Hapus catatan. Penulisnya sendiri boleh menghapus miliknya; super admin
     * boleh menghapus milik siapa pun. Catatan tidak bisa diubah — kalau salah,
     * hapus lalu tulis ulang, supaya isinya tak berubah diam-diam setelah
     * dibaca orang lain.
     */
    public function hapusCatatan(int $catatanId): void
    {
        abort_unless($this->konteks === 'staf', 403);

        $catatan = $this->order->catatan()->findOrFail($catatanId);
        $user = auth('web')->user();

        abort_unless(
            $user && ($user->hasRole('super_admin') || $catatan->user_id === $user->id),
            403,
        );

        $catatan->delete();
        unset($this->catatan);
        $this->catatanMsg = 'Catatan dihapus.';
    }

    /** Anggota tim event di cabang order (untuk di-assign). */
    #[Computed]
    public function timEventOptions()
    {
        return User::role('tim_event')
            // Anggota tim bisa memegang beberapa cabang; cocokkan pivot & kolom lama.
            ->where(fn ($q) => $q
                ->whereHas('cabangs', fn ($c) => $c->where('cabang.id', $this->order->cabang_id))
                ->orWhere('cabang_id', $this->order->cabang_id))
            ->orderBy('nama')
            ->get(['id', 'nama', 'name']);
    }

    /** Simpan penugasan tim event (sync pivot; hanya tim_event secabang). */
    public function simpanTimEvent(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);
        abort_unless($this->bisaAssignTimEvent, 403); // marketing read-only
        abort_if($this->order->terkunciUntuk(auth('web')->user()), 423);

        $ids = array_map('intval', $this->timEventTerpilih);
        // Penyaring harus sama dengan timEventOptions(), kalau tidak anggota
        // multi-cabang muncul di daftar tapi terbuang diam-diam saat disimpan.
        $valid = User::role('tim_event')
            ->where(fn ($q) => $q
                ->whereHas('cabangs', fn ($c) => $c->where('cabang.id', $this->order->cabang_id))
                ->orWhere('cabang_id', $this->order->cabang_id))
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $this->order->timEvent()->sync($valid);
        $nama = User::whereIn('id', $valid)->get()->map(fn ($u) => $u->nama ?? $u->name)->implode(', ');
        $this->order->catat('tim_event', $nama ?: 'dikosongkan');

        unset($this->order);
        $this->timMsg = 'Tim event diperbarui.';
    }

    /**
     * Status baru/dp/lunas kini OTOMATIS dari pembayaran. Aksi manual hanya
     * BATAL & aktifkan-kembali. Transisi: aktif↔batal.
     */
    public function ubahStatus(string $to): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $current = $this->order->status ?: 'baru';
        $izin = $current === OrderStatus::BATAL ? ['baru'] : ['batal'];
        abort_unless(in_array($to, $izin, true), 422);

        $this->order->update(['status' => $to]);
        if ($to === 'baru') {
            $this->order->refresh()->recalcStatusPembayaran(); // sesuaikan dgn pembayaran saat diaktifkan
        }
        $this->order->catat('status_'.$to);

        unset($this->order);
        $this->statusMsg = $to === 'batal' ? 'Order dibatalkan.' : 'Order diaktifkan kembali.';
    }

    /** Catat pembayaran (DP/pelunasan) nominal integer → status & outstanding otomatis. */
    public function catatPembayaran(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);
        abort_if($this->order->status === OrderStatus::BATAL, 422);

        // Nominal tak boleh melebihi sisa tagihan (yang belum disetujui-bayar).
        $sisa = $this->order->outstanding();

        $this->validate([
            'bayarJenis' => ['required', 'in:dp,pelunasan'],
            'bayarJumlah' => ['required', 'integer', 'min:1', 'max:'.max(1, $sisa)],
            'bayarTanggal' => ['required', 'date'],
            'bayarBukti' => ['required', 'image', 'max:4096'], // bukti WAJIB
        ], [
            'bayarJumlah.required' => 'Nominal wajib diisi.',
            'bayarJumlah.integer' => 'Nominal harus angka.',
            'bayarJumlah.max' => 'Nominal melebihi sisa tagihan (Rp'.number_format($sisa, 0, ',', '.').').',
            'bayarBukti.required' => 'Bukti bayar wajib diunggah.',
            'bayarBukti.image' => 'Bukti harus berupa gambar.',
        ]);

        $path = $this->bayarBukti->store('bukti-bayar', 'public');
        $this->order->pembayaran()->create([
            'jenis' => $this->bayarJenis,
            'jumlah' => $this->bayarJumlah,
            'status' => OrderPembayaran::STATUS_PENDING, // menunggu approval admin sales
            'tanggal_bayar' => $this->bayarTanggal,
            'dicatat_oleh' => auth('web')->id(),
            'bukti_path' => $path,
        ]);
        $this->order->catat('pembayaran_'.$this->bayarJenis, 'Rp'.number_format($this->bayarJumlah, 0, ',', '.').' (menunggu approval)');

        $this->reset(['bayarJumlah', 'bayarBukti']);
        $this->bayarTanggal = now()->toDateString();
        unset($this->order);
        $this->bayarMsg = 'Pembayaran dicatat — menunggu approval admin sales.';
    }

    /** Admin sales: setujui pembayaran (bukti valid, dana masuk). */
    public function approvePembayaran(int $pembayaranId): void
    {
        abort_unless($this->konteks === 'staf', 403);
        abort_unless($this->isAdminSales, 403);

        $bayar = $this->order->pembayaran()->findOrFail($pembayaranId);
        $bayar->update([
            'status' => OrderPembayaran::STATUS_APPROVED,
            'disetujui_oleh' => auth('web')->id(),
            'disetujui_at' => now(),
        ]);
        $this->order->load('pembayaran')->recalcStatusPembayaran();
        $this->order->catat('pembayaran_disetujui', 'Rp'.number_format($bayar->jumlah, 0, ',', '.'));

        unset($this->order);
        $this->bayarMsg = 'Pembayaran disetujui.';
    }

    /** Admin sales: tolak pembayaran (bukti tidak sah). */
    public function tolakPembayaran(int $pembayaranId): void
    {
        abort_unless($this->konteks === 'staf', 403);
        abort_unless($this->isAdminSales, 403);

        $bayar = $this->order->pembayaran()->findOrFail($pembayaranId);
        $bayar->update([
            'status' => OrderPembayaran::STATUS_DITOLAK,
            'disetujui_oleh' => auth('web')->id(),
            'disetujui_at' => now(),
        ]);
        $this->order->load('pembayaran')->recalcStatusPembayaran();
        $this->order->catat('pembayaran_ditolak', 'Rp'.number_format($bayar->jumlah, 0, ',', '.'));

        unset($this->order);
        $this->bayarMsg = 'Pembayaran ditolak.';
    }

    /** Mulai edit pembayaran (admin sales). */
    public function editPembayaran(int $pembayaranId): void
    {
        abort_unless($this->isAdminSales, 403);
        $bayar = $this->order->pembayaran()->findOrFail($pembayaranId);
        $this->editPembayaranId = $bayar->id;
        $this->editJenis = $bayar->jenis;
        $this->editJumlah = (int) $bayar->jumlah;
        $this->editTanggal = optional($bayar->tanggal_bayar)->toDateString();
        $this->editBukti = null;
        $this->resetErrorBag();
    }

    public function batalEditPembayaran(): void
    {
        $this->reset(['editPembayaranId', 'editJenis', 'editJumlah', 'editTanggal', 'editBukti']);
    }

    /** Simpan edit pembayaran → RESET ke pending (perlu approve ulang). */
    public function simpanEditPembayaran(): void
    {
        abort_unless($this->isAdminSales, 403);
        $bayar = $this->order->pembayaran()->findOrFail($this->editPembayaranId);

        // Sisa tagihan tanpa memperhitungkan pembayaran ini (agar boleh menyamai nilainya).
        $sisaTanpaIni = $this->order->outstanding()
            + ($bayar->isApproved() ? (int) $bayar->jumlah : 0);

        $this->validate([
            'editJenis' => ['required', 'in:dp,pelunasan'],
            'editJumlah' => ['required', 'integer', 'min:1', 'max:'.max(1, $sisaTanpaIni)],
            'editTanggal' => ['required', 'date'],
            'editBukti' => ['nullable', 'image', 'max:4096'], // opsional: kosong = pertahankan bukti lama
        ], [
            'editJumlah.max' => 'Nominal melebihi sisa tagihan (Rp'.number_format($sisaTanpaIni, 0, ',', '.').').',
        ]);

        $data = [
            'jenis' => $this->editJenis,
            'jumlah' => $this->editJumlah,
            'tanggal_bayar' => $this->editTanggal,
            'status' => OrderPembayaran::STATUS_PENDING, // edit → approve ulang
            'disetujui_oleh' => null,
            'disetujui_at' => null,
        ];
        if ($this->editBukti) {
            $lama = $bayar->bukti_path;
            $data['bukti_path'] = $this->editBukti->store('bukti-bayar', 'public');
            if ($lama) {
                Storage::disk('public')->delete($lama);
            }
        }
        $bayar->update($data);
        $this->order->load('pembayaran')->recalcStatusPembayaran();
        $this->order->catat('pembayaran_diedit', 'Rp'.number_format($this->editJumlah, 0, ',', '.').' (menunggu approval)');

        $this->batalEditPembayaran();
        unset($this->order);
        $this->bayarMsg = 'Pembayaran diperbarui — menunggu approval ulang.';
    }

    /**
     * Harga item order boleh dikoreksi ADMIN SALES / SUPER ADMIN meski order
     * sudah TERKUNCI — permintaan pemilik: kunci melindungi jadwal & susunan
     * item, tapi salah harga harus tetap bisa dibetulkan sampai tagihan lunas.
     * Marketing & tim event tidak boleh, dan order batal tidak diutak-atik.
     */
    private function bolehUbahHarga(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        abort_unless(auth('web')->user()?->isAdminSales(), 403);
        abort_if($this->order->status === OrderStatus::BATAL, 422);
    }

    public function mulaiEditHarga(int $itemId): void
    {
        $this->bolehUbahHarga();

        $item = $this->order->items()->where('is_free', false)->findOrFail($itemId);
        $this->hargaEditItemId = $item->id;
        $this->hargaBaru = (int) $item->harga;
        $this->hargaMsg = null;
        $this->resetErrorBag('hargaBaru');
    }

    public function batalEditHarga(): void
    {
        $this->reset(['hargaEditItemId', 'hargaBaru']);
        $this->resetErrorBag('hargaBaru');
    }

    public function simpanHarga(): void
    {
        $this->bolehUbahHarga();

        $item = $this->order->items()->where('is_free', false)->findOrFail($this->hargaEditItemId);

        $this->validate(
            ['hargaBaru' => ['required', 'integer', 'min:0']],
            [
                'hargaBaru.required' => 'Harga wajib diisi.',
                'hargaBaru.integer' => 'Harga harus angka.',
                'hargaBaru.min' => 'Harga tidak boleh minus.',
            ]
        );

        $lama = (int) $item->harga;
        $baru = (int) $this->hargaBaru;

        if ($lama === $baru) {
            $this->batalEditHarga();
            $this->hargaMsg = 'Harga tidak berubah.';

            return;
        }

        // Diskon per satuan tidak boleh melebihi harga barunya.
        $diskon = min((int) $item->diskon, $baru);
        $item->update(['harga' => $baru, 'diskon' => $diskon]);

        // Hitung ulang total dari item berbayar saja. Sengaja TIDAK memakai
        // rebuildOrder(): itu mengevaluasi ulang item free, dan aturan free
        // bisa sudah berubah sejak order dibuat — order terkunci tidak boleh
        // tiba-tiba kehilangan/menambah bonus hanya karena harga dikoreksi.
        $this->order->update([
            'total' => (int) $this->order->items()->where('is_free', false)->sum(DB::raw('harga * qty')),
        ]);

        unset($this->order);
        $this->order->load('pembayaran')->recalcStatusPembayaran();
        $this->order->catat(
            'harga_item_diubah',
            ($item->produk?->nama ?? $item->paket?->nama ?? 'Item')
                .': Rp'.number_format($lama, 0, ',', '.').' → Rp'.number_format($baru, 0, ',', '.')
        );

        unset($this->order);
        $this->batalEditHarga();
        $this->hargaMsg = 'Harga diperbarui & tagihan dihitung ulang.';
    }

    /** Validasi nominal diskon per item (≥0 & ≤ harga satuan). Return [item_id => nominal]. */
    private function diskonItemValid(): array
    {
        $out = [];
        foreach ($this->order->items->where('is_free', false) as $it) {
            $nominal = max(0, (int) ($this->diskonItem[$it->id] ?? 0));
            $out[$it->id] = min($nominal, (int) $it->harga); // tak boleh melebihi harga satuan
        }

        return $out;
    }

    /** Marketing mengajukan diskon per item → status diajukan. */
    public function ajukanDiskon(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        foreach ($this->diskonItemValid() as $itemId => $nominal) {
            $this->order->items()->whereKey($itemId)->update(['diskon_diajukan' => $nominal]);
        }
        $this->order->update(['diskon_status' => Order::DISKON_DIAJUKAN]);
        $this->order->catat('diskon_diajukan', 'diskon per item');

        unset($this->order);
        $this->diskonMsg = 'Diskon diajukan, menunggu persetujuan admin sales.';
    }

    /** Admin sales menyetujui diskon per item (boleh ubah nominal final). */
    public function setujuiDiskon(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        abort_unless(auth('web')->user()?->isAdminSales(), 403);

        foreach ($this->diskonItemValid() as $itemId => $nominal) {
            $this->order->items()->whereKey($itemId)->update(['diskon' => $nominal]);
        }
        $this->order->update(['diskon_status' => Order::DISKON_DISETUJUI]);
        unset($this->order); // refresh: computed muat ulang items dgn diskon baru

        $this->order->recalcStatusPembayaran(); // status ikut totalSetelahDiskon baru
        $this->order->catat('diskon_disetujui', 'diskon per item');

        unset($this->order);
        $this->diskonMsg = 'Diskon disetujui.';
    }

    /** Admin sales menolak pengajuan diskon (diskon item tetap). */
    public function tolakDiskon(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        abort_unless(auth('web')->user()?->isAdminSales(), 403);

        $this->order->update(['diskon_status' => Order::DISKON_DITOLAK]);
        $this->order->catat('diskon_ditolak');

        unset($this->order);
        $this->diskonMsg = 'Pengajuan diskon ditolak.';
    }

    /** Upload/ganti foto bukti bayar DP (marketing pemilik & admin sales). */
    public function uploadBuktiDp(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        $this->validate(
            ['buktiDp' => ['required', 'image', 'max:4096']],
            ['buktiDp.image' => 'File harus berupa gambar.', 'buktiDp.max' => 'Maksimal 4 MB.'],
        );

        $this->order->gantiBuktiDp($this->buktiDp);

        $this->reset('buktiDp');
        unset($this->order);
        $this->buktiMsg = 'Bukti DP tersimpan.';
    }

    /**
     * Konfirmasi milestone H-7 / H-2 oleh ADMIN SALES (area/operasional/super_admin).
     * Marketing tidak lagi berwenang. Hari-H (hh) dipindah ke tim event (Event area).
     */
    public function konfirmasiMilestone(string $key): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);

        // Hanya H-7 & H-2 di panel staf, dan hanya admin sales.
        abort_unless(in_array($key, Order::MILESTONE_ADMIN, true), 422);
        // Order susulan langsung ke hari event, tidak punya H-7/H-2.
        abort_unless(in_array($key, $this->order->milestoneBerlaku(), true), 422);
        abort_unless(auth('web')->user()->isAdminSales(), 403);
        abort_if($this->order->terkunciUntuk(auth('web')->user()), 423);
        abort_if($this->order->tanggal_event === null, 422);

        // Berurutan: DP → H-7 → H-2. Tolak bila prasyarat belum terpenuhi.
        if (! $this->order->milestoneTerbuka($key)) {
            $this->milestoneMsg = $key === 'h7'
                ? 'DP harus disetujui dulu sebelum konfirmasi H-7.'
                : 'Konfirmasi H-7 dulu sebelum H-2.';

            return;
        }

        $col = Order::MILESTONE_COL[$key];
        $this->order->update([$col => now(), Order::MILESTONE_OLEH_COL[$key] => auth('web')->id()]);
        $this->order->catat('milestone_'.$key);

        // Notifikasi WA konfirmasi (H-7/H-2) — ditahan via saklar.
        if (config('services.fonnte.kirim_konfirmasi')) {
            $this->order->kirimWa($key === 'h7'
                ? WaPesan::h7($this->order)
                : WaPesan::h2($this->order));
        }

        unset($this->order);
        $this->milestoneMsg = 'Milestone '.strtoupper($key).' dikonfirmasi.';
    }

    /** Apakah user staf sekarang admin sales (boleh konfirmasi H-7/H-2). */
    #[Computed]
    public function isAdminSales(): bool
    {
        return auth('web')->user()?->isAdminSales() ?? false;
    }

    /** Penugasan tim event = wewenang admin (super_admin/operasional/admin_sales). Marketing read-only. */
    #[Computed]
    public function bisaAssignTimEvent(): bool
    {
        return auth('web')->user()?->hasAnyRole(['super_admin', 'operasional', 'admin_sales']) ?? false;
    }

    /** Marketing/area/operasional/super_admin ubah tanggal & jam event. */
    public function simpanJadwal(): void
    {
        abort_unless($this->konteks === 'staf', 403);
        $this->authorize('update', $this->order);
        abort_if($this->order->terkunciUntuk(auth('web')->user()), 423);

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
        $with = ['items.produk', 'items.paket', 'items.desain', 'items.qcEventOleh', 'items.qcAdminOleh',
            'sekolah', 'cabang', 'marketing', 'timEvent', 'pembayaran', 'pjPapan:id,nama,name',
            'induk:id,booking_code,tanggal_event', 'susulan'];

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

    /**
     * Boleh mengoreksi redaksi & menandai item tanpa redaksi? Staf yang boleh
     * mengubah order. SENGAJA tidak ikut terkunci: redaksi justru dipakai di
     * tahap editing, sesudah Hari-H.
     */
    #[Computed]
    public function bisaUbahRedaksi(): bool
    {
        $user = auth('web')->user();

        return $this->konteks === 'staf'
            && $this->order->status !== OrderStatus::BATAL
            && ($user?->can('update', $this->order) ?? false);
    }

    public function mulaiEditRedaksi(): void
    {
        abort_unless($this->bisaUbahRedaksi, 403);

        $this->redaksiTeks = Redaksi::untuk($this->order);
        $this->redaksiMsg = null;
        $this->resetErrorBag('redaksiTeks');
        $this->redaksiEdit = true;
    }

    public function batalEditRedaksi(): void
    {
        $this->redaksiEdit = false;
        $this->resetErrorBag('redaksiTeks');
    }

    /**
     * Simpan koreksi redaksi untuk order ini saja — data sekolah tidak ikut
     * berubah. Teks yang sama persis dengan redaksi dari data sekolah disimpan
     * sebagai "ikut data sekolah", supaya perbaikan data sekolah berikutnya
     * tetap terbawa.
     */
    public function simpanRedaksi(): void
    {
        abort_unless($this->bisaUbahRedaksi, 403);

        $this->validate(
            ['redaksiTeks' => ['required', 'string', 'max:1000']],
            ['redaksiTeks.required' => 'Redaksi tidak boleh kosong.', 'redaksiTeks.max' => 'Redaksi maksimal 1000 karakter.'],
        );

        $teks = trim(str_replace("\r\n", "\n", $this->redaksiTeks));
        $koreksi = $teks === Redaksi::bawaan($this->order->sekolah) ? null : $teks;

        $this->order->update(['redaksi' => $koreksi]);
        $this->order->catat('redaksi_diubah', $koreksi === null ? 'kembali ikut data sekolah' : 'dikoreksi untuk order ini');

        unset($this->order);
        $this->redaksiEdit = false;
        $this->redaksiMsg = 'Redaksi disimpan.';
    }

    /** Kembali memakai redaksi dari data sekolah. */
    public function redaksiIkutSekolah(): void
    {
        abort_unless($this->bisaUbahRedaksi, 403);

        $this->order->update(['redaksi' => null]);
        $this->order->catat('redaksi_diubah', 'kembali ikut data sekolah');

        unset($this->order);
        $this->redaksiEdit = false;
        $this->redaksiMsg = 'Redaksi kembali mengikuti data sekolah.';
    }

    /** Tandai produk yang dicetak tanpa teks (di Trello: "PFM-008 TANPA REDAKSI"). */
    public function toggleTanpaRedaksi(int $itemId): void
    {
        abort_unless($this->bisaUbahRedaksi, 403);

        $item = $this->order->items->firstWhere('id', $itemId);
        abort_unless($item, 404);

        $item->update(['tanpa_redaksi' => ! $item->tanpa_redaksi]);
        $this->order->catat(
            'redaksi_item',
            ($item->produk?->nama ?? $item->paket?->nama ?? 'Item').': '.($item->tanpa_redaksi ? 'tanpa redaksi' : 'pakai redaksi'),
        );

        unset($this->order);
    }

    /** Path folder kerja editor per item: [order_item_id => path]. */
    #[Computed]
    public function folderItem(): array
    {
        return FolderKerja::perItem($this->order);
    }

    /**
     * Tampilkan status QC item? Baru bermakna setelah tim event mulai
     * memeriksa — sebelum itu hanya jadi deretan "belum dicek" yang bising.
     */
    #[Computed]
    public function tampilQc(): bool
    {
        return $this->konteks === 'staf'
            && ($this->order->konfirmasi_hh_at !== null
                || $this->order->items->contains(fn ($i) => $i->sudahQc('event')));
    }

    /**
     * Admin mencentang ulang item sesudah event (H+1), dicocokkan dengan
     * invoice & DO — pengganti checklist "ADMIN" di kartu Trello. Tetap bisa
     * walau order terkunci: ini pemeriksaan, bukan perubahan data order.
     */
    #[Computed]
    public function bisaQcAdmin(): bool
    {
        return $this->konteks === 'staf'
            && $this->order->konfirmasi_hh_at !== null
            && $this->order->status !== OrderStatus::BATAL
            && (auth('web')->user()?->isAdminSales() ?? false);
    }

    public function toggleQcAdmin(int $itemId): void
    {
        abort_unless($this->bisaQcAdmin, 403);
        $this->authorize('view', $this->order);

        $item = $this->order->items->firstWhere('id', $itemId);
        abort_unless($item, 404);

        $item->setQc('admin', ! $item->sudahQc('admin'), auth('web')->id());

        $order = $this->order->load('items');
        if ($item->sudahQc('admin') && $order->qcLengkap('admin')) {
            $order->catat('qc_admin_lengkap', $order->items->count().' item');
        }

        unset($this->order);
    }

    /**
     * Boleh membuat order susulan dari order ini? Peran yang sama dengan yang
     * boleh membuat booking (halaman etalase & keranjang), dan hanya untuk
     * order yang boleh diubah pengguna itu.
     */
    #[Computed]
    public function bisaBuatSusulan(): bool
    {
        $user = auth('web')->user();

        return $this->konteks === 'staf'
            && $this->order->status !== OrderStatus::BATAL
            && $user?->hasAnyRole(['super_admin', 'operasional', 'admin_sales', 'marketing'])
            && $user->can('update', $this->order);
    }

    /**
     * Mulai membuat order susulan: keranjang dikosongkan dan dikunci ke
     * sekolah order ini, lalu staf memilih item di etalase seperti biasa —
     * item susulan boleh berbeda dari induk.
     *
     * Susulan dari sebuah susulan ditautkan ke order induk ASLINYA, supaya
     * satu event tidak berubah jadi rantai yang harus ditelusuri satu per satu.
     */
    public function buatSusulan()
    {
        abort_unless($this->bisaBuatSusulan, 403);

        $induk = $this->order->isSusulan() && $this->order->induk
            ? $this->order->induk
            : $this->order;

        // Sekolah susulan selalu sama dengan sekolah order ini (dan induknya).
        app(Cart::class)->mulaiSusulan($induk->id, $this->order->sekolah_id);

        return $this->redirect(route('app.etalase.index'), navigate: true);
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
            ? Qr::svg(route('storefront.cek', $this->order->booking_code), 150)
            : null;
    }

    public function render()
    {
        return view('livewire.booking.order-detail');
    }
}
