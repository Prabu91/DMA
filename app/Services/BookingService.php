<?php

namespace App\Services;

use App\Models\Desain;
use App\Models\Order;
use App\Models\Paket;
use App\Models\Produk;
use App\Support\Cart;
use Illuminate\Support\Facades\DB;

/**
 * Logika booking: resolusi baris keranjang (harga efektif), evaluasi free
 * sekolah, dan penyimpanan order transaksional. SEMUA uang dihitung di sini
 * (server), bukan dari data sesi.
 */
class BookingService
{
    public function __construct(
        private FreeSekolahEvaluator $evaluator,
        private CodeGenerator $codeGenerator,
        private MarketingRouter $marketingRouter,
    ) {}

    /**
     * Resolusi item keranjang menjadi baris lengkap dengan harga efektif.
     * Harga efektif produk = opsi.harga_override (bila cocok) ?? produk.harga.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveLines(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->items() as $key => $it) {
            if ($it['tipe_item'] === 'paket') {
                $paket = Paket::with('items')->find($it['paket_id']);
                if (! $paket) {
                    continue;
                }
                $unit = $paket->hargaJual(); // Σ item non-free (harga × qty)
                $lines[] = [
                    'key' => $key, 'tipe' => 'paket',
                    'produk_id' => null, 'paket_id' => $paket->id,
                    'desain_id' => null, 'desain' => null, 'ukuran' => null,
                    'nama' => $paket->nama, 'qty' => $it['qty'],
                    'unit' => $unit, 'total' => $unit * $it['qty'],
                ];
            } else {
                $produk = Produk::with('opsi')->find($it['produk_id']);
                if (! $produk) {
                    continue;
                }
                $unit = (int) $produk->harga;
                if ($it['opsi_ukuran']) {
                    $opsi = $produk->opsi->firstWhere('nilai_opsi', $it['opsi_ukuran']);
                    if ($opsi && $opsi->harga_override !== null) {
                        $unit = (int) $opsi->harga_override;
                    }
                }
                $lines[] = [
                    'key' => $key, 'tipe' => 'produk',
                    'produk_id' => $produk->id, 'paket_id' => null,
                    'desain_id' => $it['desain_id'] ?? null,
                    'desain' => ($it['desain_id'] ?? null) ? optional(Desain::find($it['desain_id']))->kode : null,
                    'ukuran' => $it['opsi_ukuran'],
                    'nama' => $produk->nama, 'qty' => $it['qty'],
                    'unit' => $unit, 'total' => $unit * $it['qty'],
                ];
            }
        }

        return $lines;
    }

    public function subtotal(array $lines): int
    {
        return (int) array_sum(array_column($lines, 'total'));
    }

    /**
     * Evaluasi item free untuk booking (mekanisme A per paket + B produk satuan).
     * Menambahkan nama produk hasil untuk tampilan.
     *
     * @return array<int, array{produk_id:?int, nama:?string, ukuran:?string, qty:int, source:string}>
     */
    public function evaluasiFree(array $lines, int $jumlahSiswa, int $subtotal): array
    {
        $free = [];
        $produkUntukBonus = [];

        foreach ($lines as $l) {
            if ($l['tipe'] === 'paket') {
                // Mekanisme A: aturan paket (qty ATAU omset). omset = subtotal.
                $free = array_merge($free, $this->evaluator->evaluate([
                    'paket_id' => $l['paket_id'],
                    'jumlah_siswa' => $jumlahSiswa,
                    'total_omset' => $subtotal,
                ]));
            } else {
                $produkUntukBonus[] = ['produk_id' => $l['produk_id'], 'qty' => $l['qty']];
            }
        }

        // Mekanisme B: produk_bonus untuk produk satuan.
        if ($produkUntukBonus !== []) {
            $free = array_merge($free, $this->evaluator->evaluate(['produk' => $produkUntukBonus]));
        }

        // Lengkapi nama produk hasil.
        return array_map(function ($f) {
            $f['nama'] = $f['produk_id'] ? optional(Produk::find($f['produk_id']))->nama : null;

            return $f;
        }, $free);
    }

    /**
     * Harga unit efektif sebuah produk untuk opsi ukuran tertentu.
     * = opsi.harga_override (bila cocok) ?? produk.harga.
     */
    public function hargaProduk(Produk $produk, ?string $opsiUkuran): int
    {
        $unit = (int) $produk->harga;
        if ($opsiUkuran && $produk->relationLoaded('opsi') === false) {
            $produk->load('opsi');
        }
        if ($opsiUkuran) {
            $opsi = $produk->opsi->firstWhere('nilai_opsi', $opsiUkuran);
            if ($opsi && $opsi->harga_override !== null) {
                $unit = (int) $opsi->harga_override;
            }
        }

        return $unit;
    }

    /**
     * Hitung ulang total + item free sebuah order berdasarkan item berbayarnya
     * saat ini (dipakai saat tim event menambah/mengurangi item di lokasi).
     * Item free lama dihapus & dievaluasi ulang. Transaksional.
     */
    public function rebuildOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items');
            $paid = $order->items->where('is_free', false);

            $lines = [];
            $subtotal = 0;
            foreach ($paid as $item) {
                $subtotal += (int) $item->harga * (int) $item->qty;
                $lines[] = [
                    'tipe' => $item->tipe_item,
                    'paket_id' => $item->paket_id,
                    'produk_id' => $item->produk_id,
                    'qty' => (int) $item->qty,
                ];
            }

            // Evaluasi ulang item free.
            $order->items()->where('is_free', true)->delete();
            $freeItems = $this->evaluasiFree($lines, (int) ($order->jumlah_siswa ?? 0), $subtotal);
            foreach ($freeItems as $f) {
                $order->items()->create([
                    'tipe_item' => 'produk',
                    'produk_id' => $f['produk_id'],
                    'paket_id' => null,
                    'desain_id' => null,
                    'opsi_ukuran' => $f['ukuran'] ?? null,
                    'qty' => $f['qty'],
                    'harga' => 0,
                    'is_free' => true,
                ]);
            }

            $order->update(['total' => $subtotal]);
        });
    }

    /**
     * Simpan Order + OrderItems (paid + free) dalam satu transaksi.
     */
    public function simpan(array $ctx, array $lines, array $freeItems, int $jumlahSiswa, int $subtotal, ?string $tanggalEvent = null, ?string $jamEvent = null): Order
    {
        return DB::transaction(function () use ($ctx, $lines, $freeItems, $jumlahSiswa, $subtotal, $tanggalEvent, $jamEvent) {
            $order = Order::create([
                'sekolah_id' => $ctx['sekolah_id'],
                'marketing_id' => $ctx['marketing_id'],
                'cabang_id' => $ctx['cabang_id'],
                'sumber' => $ctx['sumber'],
                'status' => 'baru',
                'jumlah_siswa' => $jumlahSiswa,
                'total' => $subtotal, // item free = 0, jadi total = subtotal
                'tanggal_event' => $tanggalEvent ?: null,
                'jam_event' => $jamEvent ?: null,
                'tanggal_booking' => now(),
            ]);

            foreach ($lines as $l) {
                if ($l['tipe'] === 'paket') {
                    // Pecah paket → order_items produk (paid + free bawaan dari paket_item).
                    $paket = Paket::with('items')->find($l['paket_id']);
                    foreach ($paket?->items ?? [] as $pi) {
                        $order->items()->create([
                            'tipe_item' => 'produk',
                            'produk_id' => $pi->produk_id,
                            'paket_id' => $paket->id, // jejak asal paket
                            'desain_id' => $pi->desain_id,
                            'opsi_ukuran' => $pi->opsi_ukuran,
                            'qty' => (int) $pi->qty * (int) $l['qty'],
                            'harga' => $pi->is_free ? 0 : (int) $pi->harga,
                            'is_free' => (bool) $pi->is_free,
                        ]);
                    }
                } else {
                    $order->items()->create([
                        'tipe_item' => 'produk',
                        'produk_id' => $l['produk_id'],
                        'paket_id' => null,
                        'desain_id' => $l['desain_id'] ?? null,
                        'opsi_ukuran' => $l['ukuran'] ?? null,
                        'qty' => $l['qty'],
                        'harga' => $l['unit'],
                        'is_free' => false,
                    ]);
                }
            }

            foreach ($freeItems as $f) {
                $order->items()->create([
                    'tipe_item' => 'produk',
                    'produk_id' => $f['produk_id'],
                    'paket_id' => null,
                    'desain_id' => null,
                    'opsi_ukuran' => $f['ukuran'] ?? null,
                    'qty' => $f['qty'],
                    'harga' => 0,
                    'is_free' => true,
                ]);
            }

            $order->catat('dibuat', $ctx['sumber'] === 'sekolah' ? 'via portal sekolah' : 'oleh marketing');

            // Jalur sekolah: auto-assign marketing berdasarkan kecamatan sekolah
            // (admin tetap bisa override di kotak masuk). Fallback: tetap menunggu.
            if (! $order->marketing_id && $order->sumber === Order::SUMBER_SEKOLAH) {
                if ($marketing = $this->marketingRouter->forSekolah($order->sekolah)) {
                    $order->marketing_id = $marketing->id;
                    $order->save();
                    $order->catat('marketing_ditugaskan', 'otomatis via kecamatan', ['auto' => true]);
                }
            }

            // Marketing terisi (jalur marketing ATAU auto-assign) → generate booking_code.
            if ($order->marketing_id) {
                $this->codeGenerator->generate($order);
            }

            return $order;
        });
    }
}
