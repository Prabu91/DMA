<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu reaksi emoji seseorang pada sebuah komentar. */
class Reaksi extends Model
{
    protected $table = 'kanban_komentar_reaksi';

    public const UPDATED_AT = null;

    /** Pilihan emoji, seperti deretan reaksi cepat di Trello. */
    public const PILIHAN = ["\u{1F44D}", "\u{1F389}", "\u{2764}\u{FE0F}", "\u{1F604}", "\u{1F440}", "\u{1F64F}"];

    protected $fillable = ['komentar_id', 'user_id', 'emoji'];

    public function komentar(): BelongsTo
    {
        return $this->belongsTo(Komentar::class);
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
