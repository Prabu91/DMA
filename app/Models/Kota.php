<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kota extends Model
{
    protected $table = 'kota';

    protected $fillable = ['nama', 'cabang_id'];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}
