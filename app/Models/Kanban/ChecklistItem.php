<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $table = 'kanban_checklist_item';

    protected $fillable = ['checklist_id', 'teks', 'posisi', 'selesai_at', 'selesai_oleh'];

    protected function casts(): array
    {
        return ['posisi' => 'float', 'selesai_at' => 'datetime'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selesai_oleh');
    }
}
