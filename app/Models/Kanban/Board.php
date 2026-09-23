<?php

namespace App\Models\Kanban;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    protected $table = 'kanban_board';

    public const JENIS_BEBAS = 'bebas';

    public const JENIS_ORDER = 'order';

    public const VISIBILITAS = [
        'workspace' => 'All staff',
        'privat' => 'Members only',
    ];

    protected $fillable = ['nama', 'deskripsi', 'warna', 'latar_path', 'jenis', 'visibilitas', 'dibuat_oleh', 'diarsipkan_at'];

    protected function casts(): array
    {
        return ['diarsipkan_at' => 'datetime'];
    }

    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kanban_board_anggota', 'board_id', 'user_id')
            ->withPivot(['peran', 'berbintang'])
            ->withTimestamps();
    }

    public function kolom(): HasMany
    {
        return $this->hasMany(Kolom::class)->whereNull('diarsipkan_at')->orderBy('posisi');
    }

    public function semuaKolom(): HasMany
    {
        return $this->hasMany(Kolom::class);
    }

    public function kartu(): HasMany
    {
        return $this->hasMany(Kartu::class);
    }

    public function label(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('id');
    }

    /** Bidang khusus (custom fields) yang berlaku di board ini. */
    public function bidang(): HasMany
    {
        return $this->hasMany(Bidang::class)->orderBy('posisi')->orderBy('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function isOrder(): bool
    {
        return $this->jenis === self::JENIS_ORDER;
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('diarsipkan_at');
    }

    /** Board order (board sistem) — dibuat migrasi; dibuat ulang bila hilang. */
    public static function order(): self
    {
        return self::where('jenis', self::JENIS_ORDER)->orderBy('id')->first()
            ?? self::create(['nama' => 'Order', 'warna' => 'oranye', 'jenis' => self::JENIS_ORDER, 'visibilitas' => 'workspace']);
    }

    /** Catat aktivitas board / kartu. */
    public function catat(string $aksi, ?string $keterangan = null, ?Kartu $kartu = null, ?int $userId = null): void
    {
        Aktivitas::create([
            'board_id' => $this->id,
            'kartu_id' => $kartu?->id,
            'user_id' => $userId ?? auth('web')->id(),
            'aksi' => $aksi,
            'keterangan' => $keterangan !== null ? mb_substr($keterangan, 0, 500) : null,
        ]);
    }
}
