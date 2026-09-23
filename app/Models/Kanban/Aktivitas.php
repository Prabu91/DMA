<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aktivitas extends Model
{
    protected $table = 'kanban_aktivitas';

    public const UPDATED_AT = null;

    protected $fillable = ['board_id', 'kartu_id', 'user_id', 'aksi', 'keterangan'];

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(Kartu::class);
    }

    public const KALIMAT = [
        'board_dibuat' => 'created this board',
        'board_diarsipkan' => 'archived the board',
        'board_dipulihkan' => 'restored the board',
        'anggota_gabung' => 'joined the board',
        'anggota_ditambah' => 'added a member',
        'kolom_dibuat' => 'added a list',
        'kolom_diarsipkan' => 'archived a list',
        'kolom_dipulihkan' => 'restored a list',
        'kartu_dibuat' => 'added a card',
        'kartu_pindah' => 'moved a card',
        'kartu_diarsipkan' => 'archived a card',
        'kartu_dipulihkan' => 'restored a card',
        'kartu_dihapus' => 'deleted a card',
        'kartu_diubah' => 'edited a card',
        'tenggat_selesai' => 'marked the due date done',
        'checklist_selesai' => 'completed a checklist item',
        'komentar' => 'commented on',
        'lampiran' => 'attached a file to',
    ];

    /** Kalimat riwayat seperti "Fendi memindahkan kartu …". */
    public function kalimat(): string
    {
        return self::KALIMAT[$this->aksi] ?? str_replace('_', ' ', $this->aksi);
    }
}
