<?php

namespace App\Livewire\Katalog;

use App\Models\Desain;
use App\Models\Paket;
use App\Models\Produk;
use App\Support\Cart;
use App\Support\Pengaturan;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Detail item katalog (produk satuan atau paket). Untuk item berdesain,
 * menampilkan pool DESAIN kategori (filter tahun_ajaran aktif) + opsi ukuran.
 * Dipakai ulang di konteks staf & sekolah (layout dari wrapper).
 *
 * Pemilihan bersifat lokal (state UI); keranjang & simpan = Fase 3.
 */
class EtalaseDetail extends Component
{
    public string $konteks = 'staf';

    public string $tipe;   // 'produk' | 'paket'

    public int $id;

    // Pilihan lokal
    public ?int $selectedDesain = null;

    /**
     * Pilihan varian per tipe: ['box' => 'TANPA BOX', 'ukuran' => '10RP'].
     * Produk bisa punya beberapa tipe varian, jadi tiap tipe dipilih terpisah.
     */
    public array $pilihan = [];

    /**
     * Mode komposisi (Pas Foto): jumlah pcs per ukuran, mis. ['2X3' => 4, '3X4' => 2].
     * Harga tidak dipengaruhi komposisinya — satu harga produk per siswa.
     */
    public array $komposisi = [];

    public ?string $tahunAjaran = null;

    public int $qty = 1;

    public bool $justAdded = false;

    public function mount(string $konteks, string $tipe, int $id): void
    {
        abort_unless(in_array($tipe, ['produk', 'paket'], true), 404);

        $this->konteks = in_array($konteks, ['sekolah', 'publik'], true) ? $konteks : 'staf';
        $this->tipe = $tipe;
        $this->id = $id;

        // Default tahun ajaran "aktif" = tahun terbaru pada desain aktif kategori ini.
        if ($this->pakaiDesain()) {
            $this->tahunAjaran = Desain::where('kategori_id', $this->produk()->kategori_id)
                ->where('status', 'aktif')
                ->whereNotNull('tahun_ajaran')
                ->max('tahun_ajaran');
        }
    }

    #[Computed]
    public function produk(): ?Produk
    {
        return $this->tipe === 'produk'
            ? Produk::with(['kategori', 'opsi'])->findOrFail($this->id)
            : null;
    }

    #[Computed]
    public function paket(): ?Paket
    {
        return $this->tipe === 'paket'
            ? Paket::with('produk')->findOrFail($this->id)
            : null;
    }

    #[Computed]
    public function pakaiDesain(): bool
    {
        return $this->tipe === 'produk' && (bool) $this->produk()->kategori?->pakai_desain;
    }

    /**
     * Harga satuan yang benar-benar akan ditagih untuk pilihan saat ini.
     * Dihitung lewat Produk::hargaSatuan() — sumber yang sama dengan keranjang
     * & order, jadi angka di katalog tak mungkin berbeda dengan yang dibayar.
     */
    #[Computed]
    public function hargaEfektif(): int
    {
        return $this->tipe === 'produk'
            ? $this->produk()->hargaSatuan(array_values(array_filter($this->pilihan)))
            : 0;
    }

    /** Produk ini memakai pembagian jatah pcs per ukuran (Pas Foto)? */
    #[Computed]
    public function pakaiKomposisi(): bool
    {
        return $this->tipe === 'produk' && $this->produk()->pakaiKomposisiUkuran();
    }

    /** Daftar ukuran yang jatahnya dibagi (hanya pada mode komposisi). */
    #[Computed]
    public function ukuranKomposisi(): array
    {
        return $this->pakaiKomposisi ? $this->produk()->ukuranOpsi() : [];
    }

    public function maksPcs(): int
    {
        return $this->produk()->maksPcs();
    }

    #[Computed]
    public function totalPcs(): int
    {
        return (int) array_sum(array_map('intval', $this->komposisi));
    }

    /**
     * Varian yang tampil sebagai kartu "pilih salah satu".
     * Pada mode komposisi, tipe "ukuran" dikeluarkan karena ditangani
     * kartu komposisi — bukan radio.
     */
    #[Computed]
    public function variantGroups()
    {
        if ($this->tipe !== 'produk') {
            return collect();
        }

        return $this->produk()->opsi
            ->groupBy('tipe_opsi')
            ->reject(fn ($rows, $tipe) => $this->pakaiKomposisi && mb_strtolower(trim((string) $tipe)) === 'ukuran');
    }

    /**
     * Nilai varian bertipe "ukuran" — hanya tipe inilah yang menyaring desain,
     * karena pivot desain_produk menyimpan daftar ukuran yang berlaku.
     */
    #[Computed]
    public function selectedUkuran(): ?string
    {
        foreach ($this->pilihan as $tipe => $nilai) {
            if (mb_strtolower(trim((string) $tipe)) === 'ukuran' && $nilai !== '' && $nilai !== null) {
                return $nilai;
            }
        }

        return null;
    }

    #[Computed]
    public function tahunOptions(): array
    {
        if (! $this->pakaiDesain()) {
            return [];
        }

        return Desain::where('kategori_id', $this->produk()->kategori_id)
            ->where('status', 'aktif')
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->orderByDesc('tahun_ajaran')
            ->pluck('tahun_ajaran', 'tahun_ajaran')
            ->all();
    }

    #[Computed]
    public function desainPool()
    {
        if (! $this->pakaiDesain()) {
            return collect();
        }

        $pid = $this->produk()->id;

        $pool = Desain::where('status', 'aktif')
            ->when($this->tahunAjaran, fn ($q) => $q->where('tahun_ajaran', $this->tahunAjaran))
            // Desain yang ditempel ke produk ini (pivot desain_produk).
            ->whereHas('products', fn ($q) => $q->where('produk.id', $pid))
            ->with(['products' => fn ($q) => $q->where('produk.id', $pid)])
            ->orderBy('kode')
            ->get();

        // Filter ukuran: pivot.ukuran kosong = semua ukuran; selain itu harus memuat ukuran terpilih.
        $ukuran = $this->selectedUkuran;
        if ($ukuran) {
            $pool = $pool->filter(function ($d) use ($ukuran) {
                $uk = $d->products->first()?->pivot->ukuran ?? [];
                if (is_string($uk)) {
                    $uk = json_decode($uk, true) ?: [];
                }

                return empty($uk) || in_array($ukuran, $uk, true);
            })->values();
        }

        return $pool;
    }

    /** Saat pilihan varian berubah, lepas desain bila tak lagi cocok dengan ukuran. */
    public function updatedPilihan(): void
    {
        unset($this->selectedUkuran, $this->hargaEfektif, $this->desainPool); // segarkan computed
        if ($this->selectedDesain && ! $this->desainPool->contains('id', $this->selectedDesain)) {
            $this->selectedDesain = null;
        }
    }

    /** Ubah jatah pcs satu ukuran; ditahan di rentang 0..sisa jatah. */
    public function ubahPcs(string $ukuran, int $delta): void
    {
        if (! $this->pakaiKomposisi || ! in_array($ukuran, $this->ukuranKomposisi, true)) {
            return;
        }

        $sekarang = (int) ($this->komposisi[$ukuran] ?? 0);
        $lain = $this->totalPcs - $sekarang;
        $baru = max(0, min($sekarang + $delta, max(0, $this->maksPcs() - $lain)));

        if ($baru === 0) {
            unset($this->komposisi[$ukuran]);
        } else {
            $this->komposisi[$ukuran] = $baru;
        }

        unset($this->totalPcs);
    }

    /** Isian manual tetap dijaga di rentang yang sah. */
    public function updatedKomposisi(): void
    {
        unset($this->totalPcs);

        foreach ($this->komposisi as $ukuran => $jumlah) {
            if (! in_array($ukuran, $this->ukuranKomposisi, true)) {
                unset($this->komposisi[$ukuran]);
            }
        }

        $sisa = $this->maksPcs();
        foreach ($this->komposisi as $ukuran => $jumlah) {
            $nilai = max(0, min((int) $jumlah, $sisa));
            $sisa -= $nilai;

            if ($nilai === 0) {
                unset($this->komposisi[$ukuran]);
            } else {
                $this->komposisi[$ukuran] = $nilai;
            }
        }

        unset($this->totalPcs);
    }

    /** "box" -> "Box", "ukuran" -> "Ukuran" — dipakai judul kartu & pesan galat. */
    public static function labelVarian(string $tipe): string
    {
        return ucwords(mb_strtolower(trim($tipe)));
    }

    public function indexUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.katalog.index'),
            'publik' => route('storefront.katalog.index'),
            default => route('app.etalase.index'),
        };
    }

    public function keranjangUrl(): string
    {
        return match ($this->konteks) {
            'sekolah' => route('sekolah.keranjang'),
            'publik' => route('storefront.keranjang'),
            default => route('app.keranjang'),
        };
    }

    public function tambah(Cart $cart)
    {
        // Harga disembunyikan berarti pengunjung ini belum masuk. Arahkan ke
        // halaman masuk alih-alih mengisi keranjang yang harganya kosong.
        if (! Pengaturan::bolehLihatHarga()) {
            return $this->redirectRoute('sekolah.masuk', navigate: true);
        }

        $this->justAdded = false;
        $this->resetErrorBag();
        $qty = max(1, $this->qty);

        if ($this->tipe === 'paket') {
            $cart->add(['tipe_item' => 'paket', 'paket_id' => $this->id, 'qty' => $qty]);
        } else {
            $produk = $this->produk();

            // Validasi: desain wajib bila pool ada; ukuran wajib bila ada opsi is_wajib.
            if ($this->pakaiDesain() && $this->desainPool->isNotEmpty() && ! $this->selectedDesain) {
                $this->addError('selectedDesain', 'Silakan pilih desain terlebih dahulu.');
            }
            // Mode komposisi: minimal 1 pcs dan tak melebihi jatah.
            if ($this->pakaiKomposisi) {
                if ($this->totalPcs < 1) {
                    $this->addError('komposisi', 'Tentukan jumlah pcs untuk minimal satu ukuran.');
                } elseif ($this->totalPcs > $this->maksPcs()) {
                    $this->addError('komposisi', 'Total melebihi '.$this->maksPcs().' pcs per siswa.');
                }
            }

            // Tiap tipe varian yang wajib harus dipilih sendiri-sendiri.
            foreach ($this->variantGroups as $tipe => $nilaiOpsi) {
                $wajib = $nilaiOpsi->contains(fn ($o) => $o->is_wajib);
                if ($wajib && ($this->pilihan[$tipe] ?? null) === null) {
                    $this->addError('pilihan.'.$tipe, 'Silakan pilih '.self::labelVarian($tipe).' (wajib).');
                }
            }
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            // Hanya tipe yang benar-benar dipilih; urutannya ikut urutan varian produk.
            $opsi = [];
            foreach ($this->variantGroups as $tipe => $nilaiOpsi) {
                $nilai = $this->pilihan[$tipe] ?? null;
                if ($nilai !== null && $nilai !== '') {
                    $opsi[$tipe] = $nilai;
                }
            }

            // Komposisi ikut urutan ukuran produk, bukan urutan klik pembeli,
            // supaya dua pesanan dengan isi sama dianggap satu baris keranjang.
            $komposisi = [];
            foreach ($this->ukuranKomposisi as $ukuran) {
                $jumlah = (int) ($this->komposisi[$ukuran] ?? 0);
                if ($jumlah > 0) {
                    $komposisi[$ukuran] = $jumlah;
                }
            }

            $ringkas = array_map(fn ($u, $n) => $u.' ×'.$n, array_keys($komposisi), $komposisi);

            $cart->add([
                'tipe_item' => 'produk',
                'produk_id' => $this->id,
                'desain_id' => $this->selectedDesain,
                'opsi' => $opsi,
                'komposisi' => $komposisi,
                // Snapshot ringkas untuk order_items / PDF / daftar order.
                'opsi_ukuran' => implode(' · ', array_merge(array_values($opsi), $ringkas)) ?: null,
                'qty' => $qty,
            ]);
        }

        $this->justAdded = true;
        // Sertakan jumlah terbaru agar badge keranjang bisa update realtime.
        $this->dispatch('cart-updated', count: $cart->count());

        return null;
    }

    public function render()
    {
        return view('livewire.katalog.etalase-detail');
    }
}
