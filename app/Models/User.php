<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'dinas_id'])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Unit tempat user bernaung (null untuk Admin Pusat).
     */
    public function dinas(): BelongsTo
    {
        return $this->belongsTo(Dinas::class);
    }

    public function buktiDiunggah(): HasMany
    {
        return $this->hasMany(BuktiDukung::class, 'uploaded_by');
    }

    public function buktiDiverifikasi(): HasMany
    {
        return $this->hasMany(BuktiDukung::class, 'verified_by');
    }

    public function isAdminPusat(): bool
    {
        return $this->role === UserRole::AdminPusat;
    }

    public function isUnit(): bool
    {
        return $this->role === UserRole::Unit;
    }

    /**
     * ID dinas yang masuk cakupan user: Unit = unitnya sendiri; Admin Pusat =
     * null (semua unit).
     *
     * @return array<int, int>|null
     */
    public function cakupanDinasIds(): ?array
    {
        if ($this->isAdminPusat()) {
            return null;
        }

        return $this->dinas_id === null ? [] : [$this->dinas_id];
    }

    /**
     * Apakah unit tertentu masuk cakupan user ini.
     */
    public function mencakupDinas(int $dinasId): bool
    {
        $cakupan = $this->cakupanDinasIds();

        return $cakupan === null || in_array($dinasId, $cakupan, true);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
