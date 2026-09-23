<?php

namespace App\Models\Kanban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Isi satu bidang khusus pada satu kartu. */
class BidangNilai extends Model
{
    protected $table = 'kanban_bidang_nilai';

    protected $fillable = ['bidang_id', 'kartu_id', 'nilai'];

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class, 'bidang_id');
    }

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }
}
