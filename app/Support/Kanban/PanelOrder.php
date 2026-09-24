<?php

namespace App\Support\Kanban;

use App\Models\Order;

/**
 * Isi panel Order di kartu — susunannya sengaja sama persis dengan teks yang
 * selama ini diketik manual di deskripsi kartu Trello, supaya marketing tidak
 * perlu menyalin apa pun lagi dan kolom Description bebas dipakai hal lain.
 *
 * Baris yang datanya masih kosong boleh dilengkapi langsung dari kartu; yang
 * bisa diisi ditandai dengan "sumber" (tabel + kolom tujuan simpannya).
 */
final class PanelOrder
{
    /**
     * @return array<int, array{kunci: string, label: string, nilai: ?string, sumber: ?string, jenis: string}>
     */
    public static function baris(Order $order): array
    {
        $sekolah = $order->sekolah;
        $tim = $order->timEvent->map(fn ($u) => $u->nama ?? $u->name)->filter()->join(', ');

        return [
            self::baris1('nama_sekolah', 'NAMA SEKOLAH', $sekolah?->nama, null),
            self::baris1('alamat', 'ALAMAT SEKOLAH', $sekolah?->alamat, 'sekolah.alamat', 'panjang'),
            self::baris1('pic', 'PIC SEKOLAH', $sekolah?->pic_sekolah, 'sekolah.pic_sekolah'),
            self::baris1('telp', 'NO TELP PIC', $sekolah?->no_telp_pic, 'sekolah.no_telp_pic'),
            self::baris1('harga', 'HARGA', $order->total ? number_format((float) $order->total, 0, ',', '.') : null, null),
            self::baris1('tim', 'TEAM EVENT', $tim ?: null, null),
            self::baris1('tanggal', 'TANGGAL EVENT', $order->tanggal_event?->translatedFormat('j F Y'), null),
            self::baris1('jam', 'JAM EVENT', $order->jam_event, 'order.jam_event'),
            self::baris1('email', 'EMAIL GURU', $sekolah?->email_guru, 'sekolah.email_guru'),
            self::baris1('ket', 'KET', $order->keterangan, 'order.keterangan', 'panjang'),
            self::baris1('maps', 'MAPS', $sekolah?->maps_link, 'sekolah.maps_link', 'tautan'),
            self::baris1('tema', 'TEMA YEARBOOK', $order->tema_yearbook, 'order.tema_yearbook'),
        ];
    }

    /** Seluruh panel sebagai teks, untuk tombol salin. */
    public static function teks(Order $order): string
    {
        return collect(self::baris($order))
            ->map(fn (array $b) => $b['label'].': '.($b['nilai'] ?? ''))
            ->join("\n");
    }

    /** Kolom yang boleh dilengkapi dari kartu: [kunci => [tabel, kolom]]. */
    public static function isian(): array
    {
        return collect(self::baris(new Order))
            ->filter(fn (array $b) => $b['sumber'] !== null)
            ->mapWithKeys(fn (array $b) => [$b['kunci'] => explode('.', (string) $b['sumber'])])
            ->all();
    }

    private static function baris1(string $kunci, string $label, ?string $nilai, ?string $sumber, string $jenis = 'teks'): array
    {
        $nilai = is_string($nilai) ? trim($nilai) : $nilai;

        return [
            'kunci' => $kunci,
            'label' => $label,
            'nilai' => $nilai === '' ? null : $nilai,
            'sumber' => $sumber,
            'jenis' => $jenis,
        ];
    }
}
