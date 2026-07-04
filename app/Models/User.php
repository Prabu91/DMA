<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'cabang_id', 'nama', 'role', 'kode_role', 'no_telp'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Role yang melihat SEMUA cabang (dikecualikan dari CabangScope).
     */
    public const ROLES_LINTAS_CABANG = ['super_admin', 'operasional'];

    /**
     * Apakah user boleh melihat data seluruh cabang.
     * Dipakai bersama oleh CabangScope dan Policy agar konsisten.
     */
    public function seesAllCabang(): bool
    {
        return $this->hasAnyRole(self::ROLES_LINTAS_CABANG);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    /**
     * Order di mana user ini berperan sebagai marketing.
     */
    public function ordersAsMarketing(): HasMany
    {
        return $this->hasMany(Order::class, 'marketing_id');
    }

    /**
     * Order di mana user ini menjadi anggota tim event.
     */
    public function ordersAsTimEvent(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_tim_event', 'user_id', 'order_id');
    }
}
