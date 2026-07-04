<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(CabangScope::class)]
class Sekolah extends Model
{
    protected $table = 'sekolah';

    protected $fillable = [
        'id_sekolah',
        'nama',
        'alamat',
        'kota',
        'pic_sekolah',
        'no_telp_pic',
        'email_guru',
        'maps_link',
        'cabang_id',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
