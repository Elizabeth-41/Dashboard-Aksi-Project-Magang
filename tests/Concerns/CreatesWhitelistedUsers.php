<?php

namespace Tests\Concerns;

use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;

/**
 * Akun login butuh entri whitelist aktif (middleware `whitelisted`); helper
 * ini membuat keduanya sekaligus per role.
 */
trait CreatesWhitelistedUsers
{
    protected function unitUser(?Dinas $dinas = null): User
    {
        $user = User::factory()->unit($dinas ?? Dinas::factory()->create())->create();
        AllowedUser::factory()->untuk($user)->create();

        return $user;
    }

    /**
     * Admin Pusat wajib ber-2FA (middleware `2fa`), jadi default helper ini
     * sudah mengaktifkannya seperti kondisi produksi.
     */
    protected function adminPusatUser(): User
    {
        $user = User::factory()->adminPusat()->denganDuaFaktor()->create();
        AllowedUser::factory()->untuk($user)->create();

        return $user;
    }

    /**
     * Admin Pusat yang belum memasang 2FA — untuk menguji pemblokirannya.
     */
    protected function adminPusatTanpaDuaFaktor(): User
    {
        $user = User::factory()->adminPusat()->create();
        AllowedUser::factory()->untuk($user)->create();

        return $user;
    }
}
