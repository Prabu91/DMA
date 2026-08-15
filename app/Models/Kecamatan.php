<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kecamatan extends Model
{
    protected $table = 'kecamatan';

    protected $fillable = ['nama', 'kota_id'];

    public function kota(): BelongsTo
    {
        return $this->belongsTo(Kota::class);
    }

    public function sekolah(): HasMany
    {
        return $this->hasMany(Sekolah::class);
    }

    /** Marketing (user) yang menangani kecamatan ini. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_kecamatan');
    }
}
