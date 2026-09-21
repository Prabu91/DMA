<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lampiran extends Model
{
    protected $table = 'kanban_lampiran';

    protected $fillable = ['kartu_id', 'user_id', 'nama', 'path', 'thumb_path', 'url', 'mime', 'ukuran'];

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Berkas yang dipakai untuk pratinjau kecil (sampul kartu, daftar lampiran). */
    public function pathKecil(): ?string
    {
        return $this->thumb_path ?: $this->path;
    }

    /** Lampiran tautan (mis. Google Drive) tidak punya berkas di disk. */
    public function isTautan(): bool
    {
        return $this->url !== null;
    }

    public function isGambar(): bool
    {
        if ($this->isTautan()) {
            return false;
        }

        // SVG dikecualikan: bisa memuat skrip bila dibuka langsung.
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    /** "INV.jpg" → "JPG", untuk kotak jenis berkas. */
    public function ekstensi(): string
    {
        if ($this->isTautan()) {
            return 'TAUTAN';
        }

        return mb_strtoupper(pathinfo($this->nama, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
