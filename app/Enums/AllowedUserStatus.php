<?php

namespace App\Enums;

enum AllowedUserStatus: string
{
    case Aktif = 'aktif';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Dicabut => 'Dicabut',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
