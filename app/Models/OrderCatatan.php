<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan staf pada order — berupa utas. Tidak bisa diubah setelah ditulis;
 * kalau salah, dihapus lalu tulis ulang, supaya isinya tidak berubah diam-diam
 * setelah dibaca orang lain.
 */
class OrderCatatan extends Model
{
    protected $table = 'order_catatan';

    protected $fillable = [
        'order_id',
        'user_id',
        'isi',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Nama penulis; null bila akunnya sudah dihapus. */
    public function namaPenulis(): string
    {
        return $this->penulis?->nama ?: ($this->penulis?->name ?: 'Pengguna dihapus');
    }
}
