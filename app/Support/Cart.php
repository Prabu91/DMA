<?php

namespace App\Support;

/**
 * Keranjang booking berbasis session — mesin bersama untuk jalur staf & sekolah.
 *
 * Menyimpan HANYA identitas + qty + pilihan (desain/ukuran). Harga & nama
 * diresolusi ulang dari DB saat render/hitung agar selalu akurat (uang dihitung
 * di server, bukan disimpan di sesi).
 */
class Cart
{
    private string $key = 'booking_cart';

    /** @return array<string, array<string, mixed>> */
    public function items(): array
    {
        return session($this->key.'.items', []);
    }

    public function jumlahSiswa(): int
    {
        return (int) session($this->key.'.jumlah_siswa', 0);
    }

    public function sekolahId(): ?int
    {
        return session($this->key.'.sekolah_id');
    }

    public function add(array $data): void
    {
        $line = [
            'tipe_item' => $data['tipe_item'],
            'produk_id' => $data['produk_id'] ?? null,
            'paket_id' => $data['paket_id'] ?? null,
            'desain_id' => $data['desain_id'] ?? null,
            'opsi_ukuran' => $data['opsi_ukuran'] ?? null,
            'qty' => max(1, (int) ($data['qty'] ?? 1)),
        ];

        $items = $this->items();
        $key = $this->lineKey($line);

        if (isset($items[$key])) {
            $items[$key]['qty'] += $line['qty'];
        } else {
            $items[$key] = $line;
        }

        $this->putItems($items);
    }

    public function updateQty(string $key, int $qty): void
    {
        $items = $this->items();
        if (! isset($items[$key])) {
            return;
        }

        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['qty'] = $qty;
        }

        $this->putItems($items);
    }

    public function remove(string $key): void
    {
        $items = $this->items();
        unset($items[$key]);
        $this->putItems($items);
    }

    public function setJumlahSiswa(int $n): void
    {
        session([$this->key.'.jumlah_siswa' => max(0, $n)]);
    }

    public function setSekolahId(?int $id): void
    {
        session([$this->key.'.sekolah_id' => $id]);
    }

    public function clear(): void
    {
        session()->forget($this->key);
    }

    public function isEmpty(): bool
    {
        return count($this->items()) === 0;
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->items(), 'qty'));
    }

    private function putItems(array $items): void
    {
        session([$this->key.'.items' => $items]);
    }

    private function lineKey(array $line): string
    {
        return md5(implode('|', [
            $line['tipe_item'],
            $line['produk_id'] ?? '',
            $line['paket_id'] ?? '',
            $line['desain_id'] ?? '',
            $line['opsi_ukuran'] ?? '',
        ]));
    }
}
