<?php

namespace App\Models;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use Database\Factories\AllowedUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Whitelist email yang boleh login, dikelola Admin Pusat di Manajemen Akses.
 */
#[Fillable([
    'email',
    'role',
    'dinas_id',
    'status',
    'invited_by',
    'invited_at',
    'last_login_at',
])]
class AllowedUser extends Model
{
    /** @use HasFactory<AllowedUserFactory> */
    use HasFactory, LogsActivity;

    /**
     * Audit trail Manajemen Akses. last_login_at sengaja tidak dicatat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('manajemen_akses')
            ->logOnly(['email', 'role', 'dinas_id', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => $this->deskripsiAktivitas($event));
    }

    protected function deskripsiAktivitas(string $event): string
    {
        return match ($event) {
            'created' => "Menambahkan akses {$this->email} sebagai {$this->role->label()}",
            'deleted' => "Menghapus akses {$this->email}",
            'updated' => match (true) {
                $this->wasChanged('status') && $this->status === AllowedUserStatus::Dicabut => "Mencabut akses {$this->email}",
                $this->wasChanged('status') && $this->status === AllowedUserStatus::Aktif => "Mengaktifkan kembali akses {$this->email}",
                default => "Mengubah akses {$this->email} (role/unit)",
            },
            default => $event,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => AllowedUserStatus::class,
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function dinas(): BelongsTo
    {
        return $this->belongsTo(Dinas::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Akun user yang sudah terbentuk untuk email ini (jika sudah pernah login).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    #[Scope]
    protected function aktif(Builder $query): Builder
    {
        return $query->where('status', AllowedUserStatus::Aktif);
    }

    public function isAktif(): bool
    {
        return $this->status === AllowedUserStatus::Aktif;
    }
}
