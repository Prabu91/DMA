<?php

namespace App\Services\Kanban;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membuat versi kecil gambar lampiran. Sampul kartu di papan memakai versi
 * ini, jadi membuka board berisi ratusan kartu bergambar tetap ringan.
 *
 * Bila ekstensi GD tidak tersedia atau gambarnya tidak terbaca, fungsi ini
 * mengembalikan null dan pemanggilnya cukup memakai berkas aslinya.
 */
class Gambar
{
    /** Sisi terpanjang versi kecil (piksel). */
    public const SISI = 480;

    public function kecilkan(string $path, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        try {
            $isi = Storage::disk('local')->get($path);
            $asal = $isi ? @imagecreatefromstring($isi) : false;
            if (! $asal) {
                return null;
            }

            $lebar = imagesx($asal);
            $tinggi = imagesy($asal);
            $skala = min(1, self::SISI / max($lebar, $tinggi));

            // Gambar yang sudah kecil tidak perlu digandakan.
            if ($skala >= 1) {
                imagedestroy($asal);

                return null;
            }

            $baru = imagecreatetruecolor((int) round($lebar * $skala), (int) round($tinggi * $skala));
            imagealphablending($baru, false);
            imagesavealpha($baru, true);
            imagecopyresampled($baru, $asal, 0, 0, 0, 0, imagesx($baru), imagesy($baru), $lebar, $tinggi);

            ob_start();
            $mime === 'image/png' ? imagepng($baru, null, 6) : imagejpeg($baru, null, 78);
            $hasil = (string) ob_get_clean();

            imagedestroy($asal);
            imagedestroy($baru);

            $tujuan = dirname($path).'/kecil-'.Str::random(20).($mime === 'image/png' ? '.png' : '.jpg');
            Storage::disk('local')->put($tujuan, $hasil);

            return $tujuan;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
