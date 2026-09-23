<?php

namespace App\Models\Kanban;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Kartu extends Model
{
    protected $table = 'kanban_kartu';

    protected $fillable = [
        'board_id', 'kolom_id', 'posisi', 'judul', 'deskripsi', 'order_id', 'templat',
        'cover_warna', 'cover_lampiran_id', 'cover_penuh', 'mulai_pada', 'tenggat_pada',
        'tenggat_selesai_at', 'diingatkan_at', 'dibuat_oleh', 'diarsipkan_at',
    ];

    protected function casts(): array
    {
        return [
            'posisi' => 'float',
            'templat' => 'boolean',
            'cover_penuh' => 'boolean',
            'mulai_pada' => 'date',
            'tenggat_pada' => 'datetime',
            'tenggat_selesai_at' => 'datetime',
            'diingatkan_at' => 'datetime',
            'diarsipkan_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function kolom(): BelongsTo
    {
        return $this->belongsTo(Kolom::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function label(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'kanban_kartu_label', 'kartu_id', 'label_id')->orderBy('kanban_label.id');
    }

    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kanban_kartu_anggota', 'kartu_id', 'user_id');
    }

    /** Orang yang dikabari perubahan kartu ini (lonceng & email). */
    public function pengikut(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kanban_kartu_pengikut', 'kartu_id', 'user_id')->withPivot('created_at');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('posisi');
    }

    /** Semua item checklist kartu ini (untuk lencana 3/5 di papan). */
    public function checklistItem(): HasManyThrough
    {
        return $this->hasManyThrough(ChecklistItem::class, Checklist::class, 'kartu_id', 'checklist_id');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(Komentar::class)->latest()->latest('id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class)->latest()->latest('id');
    }

    /** Isi bidang khusus kartu ini. */
    public function bidangNilai(): HasMany
    {
        return $this->hasMany(BidangNilai::class, 'kartu_id');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(Aktivitas::class)->latest('created_at')->latest('id');
    }

    public function coverLampiran(): BelongsTo
    {
        return $this->belongsTo(Lampiran::class, 'cover_lampiran_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Keadaan tenggat seperti lencana Trello: selesai (hijau), lewat (merah),
     * segera (kuning, kurang dari 24 jam), atau biasa. Null bila tanpa tenggat.
     */
    public function keadaanTenggat(): ?string
    {
        if (! $this->tenggat_pada) {
            return null;
        }
        if ($this->tenggat_selesai_at) {
            return 'selesai';
        }
        if ($this->tenggat_pada->isPast()) {
            return 'lewat';
        }

        return $this->tenggat_pada->lte(now()->addDay()) ? 'segera' : 'biasa';
    }
}
