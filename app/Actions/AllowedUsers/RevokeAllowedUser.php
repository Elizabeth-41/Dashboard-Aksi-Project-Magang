<?php

namespace App\Actions\AllowedUsers;

use App\Models\AllowedUser;
use Illuminate\Support\Facades\DB;

/**
 * Hapus entri whitelist. Akun user dibiarkan ada (riwayat upload/verifikasi
 * tetap merujuk padanya) tetapi tidak bisa login lagi; sesi aktif dimatikan.
 */
class RevokeAllowedUser
{
    public function handle(AllowedUser $allowed): void
    {
        DB::transaction(function () use ($allowed) {
            $this->terminateSessions($allowed);
            $allowed->delete();
        });
    }

    /**
     * Matikan semua sesi milik akun ini di tabel sessions (driver database).
     * Untuk driver lain, middleware `whitelisted` tetap menolak request berikutnya.
     */
    public function terminateSessions(AllowedUser $allowed): void
    {
        $userId = $allowed->user?->id;

        if ($userId === null) {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->delete();
    }
}
