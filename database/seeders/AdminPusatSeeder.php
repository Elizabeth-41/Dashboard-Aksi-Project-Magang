<?php

namespace Database\Seeders;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun Admin Pusat pertama (Diskominfotik), supaya Manajemen Akses bisa
 * dipakai untuk mengundang akun lainnya. Kredensial dari env
 * ADMIN_PUSAT_EMAIL / ADMIN_PUSAT_PASSWORD.
 */
class AdminPusatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = mb_strtolower((string) env('ADMIN_PUSAT_EMAIL', 'admin@banjarmasinkota.go.id'));
        $password = (string) env('ADMIN_PUSAT_PASSWORD', 'password');

        // Semua akun wajib terikat unit; Admin Pusat berasal dari Diskominfotik
        // (cakupannya tetap seluruh unit karena ditentukan oleh role).
        $diskominfotik = Dinas::query()->where('slug', 'diskominfotik')->value('id');

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $user->name ?? env('ADMIN_PUSAT_NAME', 'Admin Pusat Diskominfotik'),
            'role' => UserRole::AdminPusat,
            'dinas_id' => $diskominfotik,
        ]);

        if (! $user->exists || ! Hash::check($password, $user->password)) {
            $user->password = Hash::make($password);
        }

        $user->save();

        AllowedUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'role' => UserRole::AdminPusat,
                'dinas_id' => $diskominfotik,
                'status' => AllowedUserStatus::Aktif,
                'invited_at' => now(),
            ],
        );

        // 2FA wajib untuk Admin Pusat: sebelum dipasang, akun ini hanya bisa
        // login dan membuka halaman pemasangan 2FA.
        $this->command?->warn("Admin Pusat {$email} harus mengaktifkan 2FA dulu sebelum bisa memakai fitur lain.");
    }
}
