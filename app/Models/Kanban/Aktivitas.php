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
        'board_dibuat' => 'membuat board ini',
        'board_diarsipkan' => 'mengarsipkan board',
        'board_dipulihkan' => 'memulihkan board',
        'anggota_gabung' => 'bergabung ke board',
        'anggota_ditambah' => 'menambahkan anggota',
        'kolom_dibuat' => 'menambahkan list',
        'kolom_diarsipkan' => 'mengarsipkan list',
        'kolom_dipulihkan' => 'memulihkan list',
        'kartu_dibuat' => 'menambahkan kartu',
        'kartu_pindah' => 'memindahkan kartu',
        'kartu_diarsipkan' => 'mengarsipkan kartu',
        'kartu_dipulihkan' => 'memulihkan kartu',
        'kartu_dihapus' => 'menghapus kartu',
        'kartu_diubah' => 'mengubah kartu',
        'tenggat_selesai' => 'menandai tenggat selesai',
        'checklist_selesai' => 'menyelesaikan item checklist',
        'komentar' => 'berkomentar di',
        'lampiran' => 'melampirkan berkas ke',
    ];

    /** Kalimat riwayat seperti "Fendi memindahkan kartu …". */
    public function kalimat(): string
    {
        return self::KALIMAT[$this->aksi] ?? str_replace('_', ' ', $this->aksi);
    }
}
