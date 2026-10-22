<?php

namespace App\Support;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menentukan penerima notifikasi berdasarkan cakupan. Hanya akun yang masih
 * ter-whitelist aktif yang dikirimi, agar akun yang dicabut tidak menerima
 * informasi internal.
 */
class PenerimaNotifikasi
{
    /**
     * Akun Unit/OPD milik sebuah dinas.
     *
     * @return Collection<int, User>
     */
    public function unitUntukDinas(Dinas $dinas): Collection
    {
        return $this->aktif()
            ->where('role', UserRole::Unit)
            ->where('dinas_id', $dinas->id)
            ->get();
    }

    /**
     * Admin Pusat aktif — satu-satunya role yang memverifikasi bukti.
     *
     * @return Collection<int, User>
     */
    public function adminPusat(): Collection
    {
        return $this->aktif()->where('role', UserRole::AdminPusat)->get();
    }

    /**
     * @return Builder<User>
     */
    protected function aktif(): Builder
    {
        return User::query()->whereIn(
            'email',
            AllowedUser::query()->where('status', AllowedUserStatus::Aktif)->select('email'),
        );
    }
}
