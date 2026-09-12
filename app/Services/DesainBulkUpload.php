<?php

namespace App\Services;

use App\Models\Desain;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Unggah banyak berkas desain sekaligus. KODE DESAIN DIAMBIL DARI NAMA BERKAS
 * (tanpa ekstensi) — permintaan pemilik, supaya mengunggah satu map berisi
 * puluhan JPEG tidak berarti mengetik puluhan kode satu per satu.
 *
 * Berkas yang kodenya sudah ada DILEWATI, bukan ditimpa atau digandakan:
 * saat mengunggah ulang satu map, yang diinginkan hampir selalu "tambahkan
 * yang belum ada", dan menimpa diam-diam bisa menukar desain di order lama.
 */
class DesainBulkUpload
{
    /** Batas jumlah berkas per unggahan. */
    public const MAKS_BERKAS = 100;

    /**
     * @param  array<int, TemporaryUploadedFile>  $files
     * @param  array<int, string>  $kodeTambahan  kode yang sudah dipakai di luar DB (mis. daftar staging)
     * @return array{dibuat: array<int, Desain>, dilewati: array<int, string>, gagal: array<int, string>}
     */
    public function jalankan(array $files, int $kategoriId, string $tahunAjaran, array $kodeTambahan = []): array
    {
        $dibuat = [];
        $dilewati = [];
        $gagal = [];

        // Kode dibandingkan tanpa membedakan huruf besar/kecil — "ERP-001" dan
        // "erp-001" adalah desain yang sama bagi manusia yang menamai berkasnya.
        $sudahAda = array_map('mb_strtolower', $kodeTambahan);

        foreach ($files as $file) {
            $kode = $this->kodeDariNamaBerkas($file->getClientOriginalName());

            if ($kode === '') {
                $gagal[] = $file->getClientOriginalName().' (nama berkas tidak bisa dipakai sebagai kode)';

                continue;
            }

            if (in_array(mb_strtolower($kode), $sudahAda, true)
                || Desain::whereRaw('lower(kode) = ?', [mb_strtolower($kode)])->exists()) {
                $dilewati[] = $kode;

                continue;
            }

            $desain = Desain::create([
                'kategori_id' => $kategoriId,
                'kode' => $kode,
                'orientasi' => $this->orientasiDari($file),
                'tahun_ajaran' => $tahunAjaran,
                'status' => 'aktif',
                'foto_preview' => $file->store('desain', 'public'),
            ]);

            $sudahAda[] = mb_strtolower($kode);
            $dibuat[] = $desain;
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati, 'gagal' => $gagal];
    }

    /** "Wisuda Gradasi 01.jpg" → "Wisuda Gradasi 01". */
    public function kodeDariNamaBerkas(string $namaBerkas): string
    {
        $kode = pathinfo($namaBerkas, PATHINFO_FILENAME);
        $kode = preg_replace('/\s+/u', ' ', (string) $kode);

        return mb_substr(trim((string) $kode), 0, 100);
    }

    /**
     * Orientasi diambil dari dimensi gambarnya, bukan ditanyakan — berkasnya
     * sendiri sudah tahu. Gambar persegi dihitung portrait (perlakuan bingkai
     * di katalog sama), dan bila dimensi tak terbaca orientasi dibiarkan null.
     */
    private function orientasiDari(TemporaryUploadedFile $file): ?string
    {
        $ukuran = @getimagesize($file->getRealPath());
        if (! $ukuran || ! $ukuran[0] || ! $ukuran[1]) {
            return null;
        }

        return $ukuran[0] > $ukuran[1] ? 'landscape' : 'portrait';
    }
}
