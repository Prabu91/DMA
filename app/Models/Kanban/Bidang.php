<?php

namespace App\Models\Kanban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bidang khusus milik board (custom field): kolom tambahan yang isinya
 * ditentukan tiap board sendiri, mis. "No. invoice" atau "Jenis paket".
 */
class Bidang extends Model
{
    protected $table = 'kanban_bidang';

    /** Jenis isian yang tersedia, seperti Custom Fields di Trello. */
    public const JENIS = [
        'teks' => 'Text',
        'angka' => 'Number',
        'tanggal' => 'Date',
        'centang' => 'Checkbox',
        'pilihan' => 'Dropdown',
    ];

    protected $fillable = ['board_id', 'nama', 'jenis', 'opsi', 'di_depan', 'posisi'];

    protected function casts(): array
    {
        return ['opsi' => 'array', 'di_depan' => 'boolean', 'posisi' => 'float'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(BidangNilai::class, 'bidang_id');
    }

    /** Tampilan nilai yang enak dibaca (untuk lencana kartu, tabel, dan ekspor). */
    public function tampilkan(?string $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        return match ($this->jenis) {
            'centang' => $nilai ? 'Yes' : 'No',
            'tanggal' => ($t = strtotime($nilai)) ? date('j M Y', $t) : $nilai,
            'angka' => rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ','),
            default => $nilai,
        };
    }
}
