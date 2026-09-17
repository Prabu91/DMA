<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu list di board — dinamai "kolom" karena "list" kata kunci PHP. */
class Kolom extends Model
{
    protected $table = 'kanban_kolom';

    protected $fillable = ['board_id', 'nama', 'posisi', 'warna', 'marketing_id', 'diarsipkan_at'];

    protected function casts(): array
    {
        return ['posisi' => 'float', 'diarsipkan_at' => 'datetime'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function kartu(): HasMany
    {
        return $this->hasMany(Kartu::class)->whereNull('diarsipkan_at')->orderBy('posisi');
    }

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_id');
    }
}
