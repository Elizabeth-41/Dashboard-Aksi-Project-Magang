<?php

namespace App\Actions\AllowedUsers;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use Illuminate\Support\Facades\DB;

/**
 * Ubah role/unit/status di whitelist dan langsung teruskan ke akun terkait.
 */
class UpdateAllowedUser
{
    public function __construct(private RevokeAllowedUser $revoke) {}

    /**
     * @param  array{role?: UserRole, dinas_id?: ?int, status?: AllowedUserStatus}  $data
     */
    public function handle(AllowedUser $allowed, array $data): AllowedUser
    {
        return DB::transaction(function () use ($allowed, $data) {
            $allowed->fill($data)->save();

            $allowed->user?->forceFill([
                'role' => $allowed->role,
                'dinas_id' => $allowed->dinas_id,
            ])->save();

            if ($allowed->status === AllowedUserStatus::Dicabut) {
                $this->revoke->terminateSessions($allowed);
            }

            return $allowed->refresh();
        });
    }
}
