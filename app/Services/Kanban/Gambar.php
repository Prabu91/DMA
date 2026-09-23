<?php

namespace App\Services\Kanban;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membuat versi kecil gambar lampiran. Sampul kartu di papan memakai versi
 * ini, jadi membuka board berisi ratusan kartu bergambar tetap ringan.
 *
 * Bila ekstensi GD tidak tersedia, gambarnya tidak terbaca, atau memorinya
 * tidak cukup, fungsi ini mengembalikan null dan pemanggilnya cukup memakai
 * berkas aslinya. Foto ponsel 24 MP butuh ~100 MB saat dibongkar GD; tanpa
 * penjagaan ini PHP berhenti mendadak (fatal error) sehingga unggahan gagal
 * tanpa keterangan apa pun.
 */
class Gambar
{
    /** Sisi terpanjang versi kecil (piksel). */
    public const SISI = 480;

    /** Sisi terpanjang latar board. */
    public const SISI_LATAR = 1600;

    /** Memori paling banyak yang boleh dipakai untuk memperkecil satu gambar. */
    public const MAKS_MEMORI = 384 * 1024 * 1024;

    public function kecilkan(string $path, string $mime, ?int $sisi = null): ?string
    {
        $sisi ??= self::SISI;

        if (! function_exists('imagecreatefromstring') || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        $memoriAwal = ini_get('memory_limit');

        try {
            $isi = Storage::disk('local')->get($path);
            if (! $isi) {
                return null;
            }

            $ukuran = @getimagesizefromstring($isi);
            if (! $ukuran) {
                return null;
            }

            [$lebar, $tinggi] = $ukuran;
            $skala = min(1, $sisi / max($lebar, $tinggi));

            // Gambar yang sudah kecil tidak perlu digandakan.
            if ($skala >= 1) {
                return null;
            }

            if (! $this->siapkanMemori($lebar, $tinggi, strlen($isi))) {
                Log::warning('Versi kecil dilewati, memori tidak cukup.', [
                    'path' => $path, 'lebar' => $lebar, 'tinggi' => $tinggi,
                ]);

                return null;
            }

            $asal = @imagecreatefromstring($isi);
            unset($isi);
            if (! $asal) {
                return null;
            }

            $baru = @imagecreatetruecolor((int) round($lebar * $skala), (int) round($tinggi * $skala));
            if (! $baru) {
                imagedestroy($asal);

                return null;
            }

            imagealphablending($baru, false);
            imagesavealpha($baru, true);
            imagecopyresampled($baru, $asal, 0, 0, 0, 0, imagesx($baru), imagesy($baru), $lebar, $tinggi);
            imagedestroy($asal);

            ob_start();
            $mime === 'image/png' ? imagepng($baru, null, 6) : imagejpeg($baru, null, 78);
            $hasil = (string) ob_get_clean();
            imagedestroy($baru);

            $tujuan = dirname($path).'/kecil-'.Str::random(20).($mime === 'image/png' ? '.png' : '.jpg');
            Storage::disk('local')->put($tujuan, $hasil);

            return $tujuan;
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            $this->kembalikanMemori($memoriAwal);
        }
    }

    /**
     * Kembalikan batas memori semula. Kalau memori yang sudah terlanjur
     * diminta dari sistem masih di atas batas lama, PHP menolak menurunkannya
     * — biarkan saja, karena batas ini hanya berlaku untuk permintaan ini.
     */
    private function kembalikanMemori(string|false $semula): void
    {
        if ($semula === false || $semula === '') {
            return;
        }

        $batas = $this->keByte($semula);

        if ($batas < 0 || memory_get_usage(true) < $batas) {
            @ini_set('memory_limit', $semula);
        }
    }

    /**
     * Pastikan memori cukup untuk membongkar gambar sebesar ini — kalau perlu
     * batasnya dinaikkan sementara, tapi tidak melewati MAKS_MEMORI.
     */
    public function siapkanMemori(int $lebar, int $tinggi, int $besarBerkas): bool
    {
        // GD memakai 4 byte per piksel; saat membongkar JPEG/PNG ia sempat
        // memakai lebih dari itu, jadi perkiraannya dilebihkan.
        $butuh = (int) ($lebar * $tinggi * 4 * 1.6) + $besarBerkas + (32 * 1024 * 1024);
        $batas = $this->batasMemori();

        if ($batas < 0) {
            return true;
        }

        $perlu = memory_get_usage(true) + $butuh;

        if ($perlu <= $batas) {
            return true;
        }

        if ($perlu > self::MAKS_MEMORI) {
            return false;
        }

        return ini_set('memory_limit', (int) ceil($perlu / 1048576).'M') !== false;
    }

    /** memory_limit yang sedang berlaku, dalam byte. */
    private function batasMemori(): int
    {
        return $this->keByte((string) ini_get('memory_limit'));
    }

    /** Ubah tulisan seperti "128M" jadi byte; -1 berarti tanpa batas. */
    private function keByte(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '' || $nilai === '-1') {
            return -1;
        }

        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024 * 1024 * 1024,
            'm' => $angka * 1024 * 1024,
            'k' => $angka * 1024,
            default => $angka,
        };
    }
}
