<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Komentar extends Model
{
    protected $table = 'kanban_komentar';

    protected $fillable = ['kartu_id', 'user_id', 'isi', 'diubah_at'];

    protected function casts(): array
    {
        return ['diubah_at' => 'datetime'];
    }

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }

    public function reaksi(): HasMany
    {
        return $this->hasMany(Reaksi::class, 'komentar_id');
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
