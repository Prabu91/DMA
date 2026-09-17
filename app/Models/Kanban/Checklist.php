<?php

namespace App\Models\Kanban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checklist extends Model
{
    protected $table = 'kanban_checklist';

    protected $fillable = ['kartu_id', 'judul', 'posisi'];

    protected function casts(): array
    {
        return ['posisi' => 'float'];
    }

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('posisi');
    }
}
