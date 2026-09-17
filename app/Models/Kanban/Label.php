<?php

namespace App\Models\Kanban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Label extends Model
{
    protected $table = 'kanban_label';

    protected $fillable = ['board_id', 'nama', 'warna'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
