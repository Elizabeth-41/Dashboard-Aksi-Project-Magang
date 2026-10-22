<?php

namespace App\Enums;

enum UserRole: string
{
    case Unit = 'unit';
    case AdminPusat = 'admin_pusat';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unit / OPD',
            self::AdminPusat => 'Admin Pusat',
        };
    }

    public function isAdminPusat(): bool
    {
        return $this === self::AdminPusat;
    }

    /**
     * Admin Pusat memegang akses penuh ke semua unit dan Manajemen Akses,
     * jadi 2FA wajib; role lain boleh mengaktifkannya secara sukarela.
     */
    public function requiresTwoFactor(): bool
    {
        return $this === self::AdminPusat;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
