<?php

namespace App\Services\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Label;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\Kanban\Posisi;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga satu kartu per order di board Order — pengganti board A di Trello.
 *
 * Kartu baru masuk ke list milik marketing order itu (list dibuat otomatis).
 * Sesudahnya kartu bebas diseret ke list mana pun; sinkron hanya memindahkan
 * kartu bila marketing order diganti DAN kartu masih di list marketing lama.
 */
class SinkronOrder
{
    public const LIST_TANPA_MARKETING = 'Tanpa marketing';

    public const LABEL_SUSULAN = 'Susulan';

    public function sinkron(Order $order): ?Kartu
    {
        return DB::transaction(function () use ($order) {
            $kartu = Kartu::where('order_id', $order->id)->lockForUpdate()->first();
            $mati = $order->trashed() || $order->status === OrderStatus::BATAL;

            if (! $kartu) {
                // Order batal/terhapus tidak perlu kartu baru.
                return $mati ? null : $this->buatKartu($order);
            }

            $this->perbarui($kartu, $order, $mati);

            return $kartu;
        });
    }

    /** Judul kartu seperti di Trello: {ID sekolah}_{NAMA SEKOLAH}_{MARKETING}. */
    public function judul(Order $order): string
    {
        $sekolah = Sekolah::withoutGlobalScopes()->find($order->sekolah_id);
        $marketing = $order->marketing_id ? User::find($order->marketing_id) : null;

        $bagian = array_filter([
            $sekolah?->id_sekolah,
            mb_strtoupper((string) ($sekolah?->nama ?? 'Sekolah #'.$order->sekolah_id)),
            $marketing ? mb_strtoupper((string) ($marketing->nama ?? $marketing->name)) : null,
        ], fn ($v) => $v !== null && $v !== '');

        return mb_substr(implode('_', $bagian), 0, 255);
    }

    private function buatKartu(Order $order): Kartu
    {
        $board = Board::order();
        $kolom = $this->listMarketing($board, $order->marketing_id);

        $kartu = Kartu::create([
            'board_id' => $board->id,
            'kolom_id' => $kolom->id,
            'posisi' => (float) Kartu::where('kolom_id', $kolom->id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK,
            'judul' => $this->judul($order),
            'order_id' => $order->id,
        ]);

        if ($order->isSusulan()) {
            $kartu->label()->attach($this->labelSusulan($board)->id);
        }

        $board->catat('kartu_dibuat', 'dari order '.$order->booking_code.' ke list '.$kolom->nama, $kartu);

        return $kartu;
    }

    private function perbarui(Kartu $kartu, Order $order, bool $mati): void
    {
        $board = $kartu->board;
        $ubah = [];

        if ($order->wasChanged(['sekolah_id', 'marketing_id'])) {
            $ubah['judul'] = $this->judul($order);
        }

        // Ganti marketing: ikutkan kartu hanya bila masih di list marketing lama.
        if ($order->wasChanged('marketing_id') && $board->isOrder()) {
            $lama = $order->getOriginal('marketing_id');
            $kolomSekarang = Kolom::find($kartu->kolom_id);
            if ($kolomSekarang && $this->milikMarketing($kolomSekarang, $lama)) {
                $baru = $this->listMarketing($board, $order->marketing_id);
                $ubah['kolom_id'] = $baru->id;
                $ubah['posisi'] = (float) Kartu::where('kolom_id', $baru->id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK;
            }
        }

        if ($mati && ! $kartu->diarsipkan_at) {
            $ubah['diarsipkan_at'] = now();
            $board->catat('kartu_diarsipkan', $order->trashed() ? 'order dihapus' : 'order dibatalkan', $kartu);
        } elseif (! $mati && $kartu->diarsipkan_at && $this->diarsipkanSistem($kartu)) {
            $ubah['diarsipkan_at'] = null;
            $board->catat('kartu_dipulihkan', 'order aktif lagi', $kartu);
        }

        if ($ubah) {
            $kartu->update($ubah);
        }
    }

    /** Hanya pulihkan kartu yang diarsipkan karena order batal/dihapus, bukan yang diarsipkan orang. */
    private function diarsipkanSistem(Kartu $kartu): bool
    {
        $terakhir = $kartu->aktivitas()->where('aksi', 'kartu_diarsipkan')->first();

        return in_array($terakhir?->keterangan, ['order dihapus', 'order dibatalkan'], true);
    }

    private function milikMarketing(Kolom $kolom, ?int $marketingId): bool
    {
        return $marketingId
            ? (int) $kolom->marketing_id === (int) $marketingId
            : $kolom->marketing_id === null && $kolom->nama === self::LIST_TANPA_MARKETING;
    }

    /** List untuk marketing ini di board order; dibuat (atau dipulihkan) bila belum ada. */
    public function listMarketing(Board $board, ?int $marketingId): Kolom
    {
        $cari = Kolom::where('board_id', $board->id)
            ->when($marketingId,
                fn ($q) => $q->where('marketing_id', $marketingId),
                fn ($q) => $q->whereNull('marketing_id')->where('nama', self::LIST_TANPA_MARKETING))
            ->orderByRaw('diarsipkan_at is not null')
            ->orderBy('id');

        $kolom = $cari->first();
        if ($kolom) {
            if ($kolom->diarsipkan_at) {
                $kolom->update(['diarsipkan_at' => null, 'posisi' => $this->posisiAkhir($board)]);
            }

            return $kolom;
        }

        return Kolom::create([
            'board_id' => $board->id,
            'nama' => $this->namaList($marketingId),
            'posisi' => $this->posisiAkhir($board),
            'marketing_id' => $marketingId,
        ]);
    }

    private function namaList(?int $marketingId): string
    {
        $marketing = $marketingId ? User::with('cabang:id,nama')->find($marketingId) : null;
        if (! $marketing) {
            return self::LIST_TANPA_MARKETING;
        }

        $nama = $marketing->nama ?? $marketing->name;

        return $marketing->cabang ? $nama.' ('.$marketing->cabang->nama.')' : $nama;
    }

    private function posisiAkhir(Board $board): float
    {
        return (float) Kolom::where('board_id', $board->id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK;
    }

    private function labelSusulan(Board $board): Label
    {
        return Label::firstOrCreate(['board_id' => $board->id, 'nama' => self::LABEL_SUSULAN], ['warna' => 'ungu']);
    }
}
