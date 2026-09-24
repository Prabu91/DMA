<?php

namespace App\Services\Kanban;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Thumbnail bawaan marketing: gambar yang otomatis dipasang sebagai cover
 * kartu order milik marketing tersebut, seperti template bergambar di Trello
 * yang dipakai supaya sekali lihat ketahuan kartu itu milik siapa.
 */
class CoverMarketing
{
    /** Sisi terpanjang gambar yang disimpan. */
    public const SISI = 1200;

    public function simpan(User $marketing, UploadedFile $berkas): string
    {
        $mime = (string) $berkas->getMimeType();
        $path = $berkas->store('kanban/cover-marketing', 'local');

        // Gambar besar dikecilkan; kalau gagal, berkas aslinya tetap dipakai.
        if ($kecil = app(Gambar::class)->kecilkan($path, $mime, self::SISI)) {
            Storage::disk('local')->delete($path);
            $path = $kecil;
        }

        $this->hapus($marketing);

        $marketing->kanban_cover_path = $path;
        $marketing->save();

        return $path;
    }

    public function hapus(User $marketing): void
    {
        if ($lama = $marketing->kanban_cover_path) {
            Storage::disk('local')->delete($lama);
            $marketing->kanban_cover_path = null;
            $marketing->save();
        }
    }

    /** Tanda berkas yang dipakai di URL, supaya peramban memuat ulang saat gambarnya diganti. */
    public function cap(User $marketing): string
    {
        return substr(md5((string) $marketing->kanban_cover_path), 0, 8);
    }

    public function punyaCover(?User $marketing): bool
    {
        return $marketing !== null
            && $marketing->kanban_cover_path
            && Storage::disk('local')->exists($marketing->kanban_cover_path);
    }

    /** Nama berkas unduhan/ETag sederhana. */
    public function nama(User $marketing): string
    {
        return Str::slug($marketing->nama ?? $marketing->name ?: 'marketing').'.jpg';
    }
}
