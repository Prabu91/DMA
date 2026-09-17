<?php

namespace App\Support;

use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Path folder kerja editor di server kantor, disusun dari data order.
 *
 * Foto hasil event TIDAK disimpan di aplikasi — tetap di server kantor. Yang
 * dibuat di sini hanya path-nya, supaya tiap orang tidak mengetik path sendiri
 * dengan penamaan yang berbeda-beda (di Trello path ini ditulis manual di
 * komentar kartu).
 *
 * Polanya berupa templat yang bisa diubah admin di halaman Pengaturan, karena
 * sebagian bagian path contoh dari DMA belum pasti artinya. Contoh dari Trello:
 *   \\delapanmataair\Editor 5\2. REGULER#PROJECT SEKOLAH\2026-2027\
 *   03 SEPTEMBER 2026\BANDUNG (SHANTY)\10_TK MIFTAHUL KHOIR\PILIHAN\
 *   1. FOTO 10RP_OB PROFESI_GRADASI
 */
final class FolderKerja
{
    public const KUNCI_ROOT = 'folder_kerja_root';

    public const KUNCI_TEMPLAT_SEKOLAH = 'folder_kerja_templat_sekolah';

    public const KUNCI_TEMPLAT_ITEM = 'folder_kerja_templat_item';

    /** Kunci folder jalur per grup kategori: folder_kerja_jalur_{grup}. */
    public const PREFIKS_JALUR = 'folder_kerja_jalur_';

    public const BAWAAN_ROOT = '\\\\delapanmataair\\Editor 5';

    public const BAWAAN_TEMPLAT_SEKOLAH = '{root}\\{jalur}\\{tahun_ajaran}\\{tanggal_event}\\{cabang} ({marketing})\\{sekolah}';

    public const BAWAAN_TEMPLAT_ITEM = '{folder_sekolah}\\PILIHAN\\{no}. {item}';

    /** Hanya "reguler" yang diketahui dari contoh DMA; sisanya perlu dikonfirmasi. */
    public const BAWAAN_JALUR = [
        'reguler' => '2. REGULER#PROJECT SEKOLAH',
        'ob' => 'OPENBOOTH',
        'yb' => 'YEARBOOK',
        'souvenir' => 'SOUVENIR',
    ];

    /** Penanda yang boleh dipakai, beserta keterangannya (untuk halaman Pengaturan). */
    public const PENANDA_SEKOLAH = [
        '{root}' => 'Folder server kantor',
        '{jalur}' => 'Folder jalur produk (per grup kategori)',
        '{tahun_ajaran}' => 'Tahun ajaran dari tanggal event, mis. 2026-2027',
        '{tanggal_event}' => 'Tanggal event, mis. 10 SEPTEMBER 2026',
        '{bulan_event}' => 'Bulan event, mis. SEPTEMBER 2026',
        '{cabang}' => 'Nama cabang, mis. BANDUNG',
        '{marketing}' => 'Nama depan marketing, mis. SHANTY',
        '{marketing_lengkap}' => 'Nama lengkap marketing',
        '{sekolah}' => 'Nama sekolah',
        '{id_sekolah}' => 'ID sekolah',
        '{kode_booking}' => 'Kode booking',
    ];

    public const PENANDA_ITEM = [
        '{folder_sekolah}' => 'Hasil templat folder sekolah',
        '{no}' => 'Nomor urut item di order (1, 2, 3, …)',
        '{item}' => 'Nama produk + opsinya, mis. FOTO 10RP OB PROFESI',
        '{produk}' => 'Nama produk saja',
        '{opsi}' => 'Opsi/ukuran saja',
        '{desain}' => 'Kode desain',
    ];

    private const BULAN = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
    ];

    public static function root(): string
    {
        return Pengaturan::teks(self::KUNCI_ROOT) ?: self::BAWAAN_ROOT;
    }

    public static function templatSekolah(): string
    {
        return Pengaturan::teks(self::KUNCI_TEMPLAT_SEKOLAH) ?: self::BAWAAN_TEMPLAT_SEKOLAH;
    }

    public static function templatItem(): string
    {
        return Pengaturan::teks(self::KUNCI_TEMPLAT_ITEM) ?: self::BAWAAN_TEMPLAT_ITEM;
    }

    public static function jalur(?string $grup): string
    {
        $grup = array_key_exists((string) $grup, self::BAWAAN_JALUR) ? $grup : 'reguler';

        return Pengaturan::teks(self::PREFIKS_JALUR.$grup) ?: self::BAWAAN_JALUR[$grup];
    }

    /**
     * Path folder tiap item order: [order_item_id => path]. Item diberi nomor
     * urut sesuai urutan item di order, termasuk item free — sama seperti
     * penomoran checklist di kartu Trello.
     *
     * @return array<int, string>
     */
    public static function perItem(Order $order): array
    {
        $order->loadMissing(['items.produk.kategori', 'items.desain', 'sekolah', 'cabang', 'marketing']);

        $hasil = [];
        foreach ($order->items->values() as $i => $item) {
            $hasil[$item->id] = self::isi(self::templatItem(), [
                '{folder_sekolah}' => self::folderSekolah($order, $item->produk?->kategori?->grup),
                '{no}' => (string) ($i + 1),
                '{item}' => self::bersih(trim(($item->produk?->nama ?? $item->paket?->nama ?? 'ITEM').' '.$item->opsi_ukuran)),
                '{produk}' => self::bersih($item->produk?->nama ?? $item->paket?->nama ?? 'ITEM'),
                '{opsi}' => self::bersih((string) $item->opsi_ukuran),
                '{desain}' => self::bersih((string) $item->desain?->kode),
            ]);
        }

        return $hasil;
    }

    /**
     * Folder sekolah per jalur produk yang ada di order: [grup => path].
     * Order campuran (mis. foto reguler + yearbook) punya lebih dari satu.
     *
     * @return array<string, string>
     */
    public static function perJalur(Order $order): array
    {
        $order->loadMissing(['items.produk.kategori', 'sekolah', 'cabang', 'marketing']);

        return $order->items
            ->map(fn (OrderItem $i) => $i->produk?->kategori?->grup ?? 'reguler')
            ->unique()
            ->mapWithKeys(fn ($grup) => [$grup => self::folderSekolah($order, $grup)])
            ->all();
    }

    public static function labelJalur(string $grup): string
    {
        return Kategori::grupLabel($grup);
    }

    public static function folderSekolah(Order $order, ?string $grup): string
    {
        $tanggal = $order->tanggal_event;
        $marketing = trim((string) ($order->marketing?->nama ?? $order->marketing?->name));

        return self::isi(self::templatSekolah(), [
            '{root}' => self::root(),
            '{jalur}' => self::jalur($grup),
            '{tahun_ajaran}' => $tanggal ? self::tahunAjaran($tanggal->year, $tanggal->month) : 'TANPA-TAHUN',
            '{tanggal_event}' => $tanggal ? sprintf('%02d %s %d', $tanggal->day, self::BULAN[$tanggal->month], $tanggal->year) : 'TANPA TANGGAL',
            '{bulan_event}' => $tanggal ? self::BULAN[$tanggal->month].' '.$tanggal->year : 'TANPA TANGGAL',
            '{cabang}' => self::bersih(preg_replace('/^DMA\s+/i', '', (string) $order->cabang?->nama)),
            '{marketing}' => self::bersih(strtok($marketing, ' ') ?: 'TANPA MARKETING'),
            '{marketing_lengkap}' => self::bersih($marketing ?: 'TANPA MARKETING'),
            '{sekolah}' => self::bersih((string) $order->sekolah?->nama),
            '{id_sekolah}' => self::bersih((string) $order->sekolah?->id_sekolah),
            '{kode_booking}' => self::bersih((string) ($order->booking_code ?? 'ORDER-'.$order->id)),
        ]);
    }

    /** Tahun ajaran berganti setiap Juli: September 2026 → 2026-2027. */
    public static function tahunAjaran(int $tahun, int $bulan): string
    {
        return $bulan >= 7 ? $tahun.'-'.($tahun + 1) : ($tahun - 1).'-'.$tahun;
    }

    /** Penanda di templat yang tidak dikenal (untuk validasi di Pengaturan). */
    public static function penandaTakDikenal(string $templat, array $dikenal): array
    {
        preg_match_all('/\{[a-z_]+\}/', $templat, $m);

        return array_values(array_diff(array_unique($m[0]), array_keys($dikenal)));
    }

    /**
     * Isi templat. Nilai yang berasal dari data sudah dibersihkan lewat bersih()
     * sebelum masuk; root, jalur, dan folder_sekolah memang berupa path (diatur
     * admin) sehingga dimasukkan apa adanya.
     */
    private static function isi(string $templat, array $nilai): string
    {
        return strtr($templat, $nilai);
    }

    /** Huruf besar + buang karakter terlarang di nama folder Windows. */
    private static function bersih(string $teks): string
    {
        $teks = preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $teks);
        $teks = preg_replace('/\s+/u', ' ', (string) $teks);

        return mb_strtoupper(trim((string) $teks, ' .-'));
    }
}
