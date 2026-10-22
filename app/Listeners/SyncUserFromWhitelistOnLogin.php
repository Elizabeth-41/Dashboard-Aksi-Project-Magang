<?php

namespace App\Listeners;

use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Setelah login penuh (termasuk lolos 2FA): catat waktu login terakhir di
 * whitelist dan pastikan role/dinas akun mengikuti whitelist sebagai sumber
 * kebenaran, sehingga perubahan di Manajemen Akses berlaku pada login berikutnya.
 */
class SyncUserFromWhitelistOnLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $allowed = AllowedUser::query()->where('email', $user->email)->first();

        if ($allowed === null) {
            return;
        }

        $allowed->forceFill(['last_login_at' => now()])->save();

        $user->forceFill([
            'role' => $allowed->role,
            'dinas_id' => $allowed->dinas_id,
        ])->save();
    }
}
