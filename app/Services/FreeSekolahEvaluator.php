<?php

namespace App\Services;

use App\Models\AturanFreeSekolah;
use App\Models\ProdukBonus;

/**
 * Menghitung item FREE untuk sebuah konteks order sekolah.
 *
 * DUA mekanisme:
 *  A. aturan_free_sekolah — kondisional, KHUSUS PAKET, basis qty atau omset.
 *  B. produk_bonus        — bonus tetap yang menempel pada produk (satuan).
 *
 * Class ini murni "kalkulator": tidak menyentuh order/booking. Aturan adalah
 * DATA (diubah admin tanpa ubah kode).
 */
class FreeSekolahEvaluator
{
    /**
     * @param  array{
     *   paket_id?: int|null,
     *   jumlah_siswa?: int,
     *   total_omset?: int,
     *   produk?: array<int, array{produk_id:int, qty?:int}>
     * }  $context
     * @return array<int, array{produk_id:int|null, ukuran:?string, qty:int, source:string}>
     */
    public function evaluate(array $context): array
    {
        return array_merge(
            $this->fromAturanPaket($context),
            $this->fromProdukBonus($context),
        );
    }

    /**
     * Mekanisme A — aturan kondisional pada paket (qty ATAU omset).
     */
    private function fromAturanPaket(array $context): array
    {
        $paketId = $context['paket_id'] ?? null;
        if (! $paketId) {
            return [];
        }

        $jumlahSiswa = (int) ($context['jumlah_siswa'] ?? 0);
        $totalOmset = (int) ($context['total_omset'] ?? 0);

        $free = [];
        foreach (AturanFreeSekolah::where('paket_id', $paketId)->get() as $aturan) {
            $nilaiKonteks = $aturan->basis === 'omset' ? $totalOmset : $jumlahSiswa;

            if ($this->cocok($nilaiKonteks, $aturan->operator, (int) $aturan->nilai)) {
                $free[] = [
                    'produk_id' => $aturan->hasil_produk_id,
                    'ukuran' => $aturan->hasil_ukuran,
                    'qty' => 1,
                    'source' => 'aturan',
                ];
            }
        }

        return $free;
    }

    /**
     * Mekanisme B — bonus tetap per produk satuan.
     * Qty bonus = qty pada produk_bonus × qty produk yang dipesan (per unit).
     */
    private function fromProdukBonus(array $context): array
    {
        $free = [];
        foreach ($context['produk'] ?? [] as $item) {
            $produkId = $item['produk_id'] ?? null;
            if (! $produkId) {
                continue;
            }
            $qtyDipesan = (int) ($item['qty'] ?? 1);

            foreach (ProdukBonus::where('produk_id', $produkId)->get() as $bonus) {
                $free[] = [
                    'produk_id' => $bonus->bonus_produk_id,
                    'ukuran' => null,
                    'qty' => (int) $bonus->qty * $qtyDipesan,
                    'source' => 'bonus',
                ];
            }
        }

        return $free;
    }

    private function cocok(int $nilai, string $operator, int $ambang): bool
    {
        return match ($operator) {
            '>=' => $nilai >= $ambang,
            '<' => $nilai < $ambang,
            '>' => $nilai > $ambang,
            '<=' => $nilai <= $ambang,
            '==' => $nilai === $ambang,
            default => false,
        };
    }
}
