<?php

namespace App\Actions\AllowedUsers;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\User;
use App\Notifications\UndanganAkses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Tambah email ke whitelist sekaligus siapkan akunnya. Akun dibuat dengan
 * kata sandi acak, lalu user diundang menyetel kata sandinya sendiri lewat
 * tautan reset password.
 */
class InviteAllowedUser
{
    /**
     * @param  array{email: string, role: UserRole, dinas_id: ?int, name?: ?string}  $data
     */
    public function handle(array $data, User $invitedBy): AllowedUser
    {
        $email = Str::lower($data['email']);

        [$allowed, $user] = DB::transaction(function () use ($data, $email, $invitedBy) {
            $allowed = AllowedUser::create([
                'email' => $email,
                'role' => $data['role'],
                'dinas_id' => $data['dinas_id'],
                'status' => AllowedUserStatus::Aktif,
                'invited_by' => $invitedBy->id,
                'invited_at' => now(),
            ]);

            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->name = $data['name'] ?? Str::before($email, '@');
                $user->password = Str::password();
            }

            $user->forceFill([
                'role' => $data['role'],
                'dinas_id' => $data['dinas_id'],
            ])->save();

            return [$allowed, $user];
        });

        // Tautan undangan memakai token reset password yang sama, tetapi isi
        // emailnya berupa undangan berbahasa Indonesia.
        $user->notify(new UndanganAkses(Password::broker()->createToken($user), $allowed->loadMissing('dinas')));

        return $allowed;
    }
}
