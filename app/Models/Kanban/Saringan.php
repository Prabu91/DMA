<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saringan kartu yang disimpan seseorang di sebuah board. */
class Saringan extends Model
{
    protected $table = 'kanban_saringan';

    protected $fillable = ['board_id', 'user_id', 'nama', 'isi'];

    protected function casts(): array
    {
        return ['isi' => 'array'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
