<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lampiran extends Model
{
    protected $table = 'kanban_lampiran';

    protected $fillable = ['kartu_id', 'user_id', 'nama', 'path', 'mime', 'ukuran'];

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isGambar(): bool
    {
        // SVG dikecualikan: bisa memuat skrip bila dibuka langsung.
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    /** "INV.jpg" → "JPG", untuk kotak jenis berkas. */
    public function ekstensi(): string
    {
        return mb_strtoupper(pathinfo($this->nama, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
