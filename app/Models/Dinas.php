<?php

namespace App\Models;

use Database\Factories\DinasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Unit / OPD (leading sector).
 */
#[Table('dinas')]
#[Fillable(['nama', 'slug'])]
class Dinas extends Model
{
    /** @use HasFactory<DinasFactory> */
    use HasFactory;

    /**
     * URL dan filter unit memakai slug, bukan ID auto increment.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function rencanaAksi(): HasMany
    {
        return $this->hasMany(RencanaAksi::class);
    }

    public function buktiDukung(): HasManyThrough
    {
        return $this->hasManyThrough(BuktiDukung::class, RencanaAksi::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allowedUsers(): HasMany
    {
        return $this->hasMany(AllowedUser::class);
    }
}
