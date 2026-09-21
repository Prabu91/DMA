<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $table = 'kanban_checklist_item';

    protected $fillable = ['checklist_id', 'teks', 'posisi', 'selesai_at', 'selesai_oleh', 'user_id', 'tenggat_pada'];

    protected function casts(): array
    {
        return ['posisi' => 'float', 'selesai_at' => 'datetime', 'tenggat_pada' => 'datetime'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selesai_oleh');
    }

    /** Orang yang ditugaskan mengerjakan item ini. */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Keadaan tenggat item: selesai, lewat, segera, biasa — atau null bila tanpa tenggat. */
    public function keadaanTenggat(): ?string
    {
        if (! $this->tenggat_pada) {
            return null;
        }
        if ($this->selesai_at) {
            return 'selesai';
        }
        if ($this->tenggat_pada->isPast()) {
            return 'lewat';
        }

        return $this->tenggat_pada->lte(now()->addDay()) ? 'segera' : 'biasa';
    }
}
