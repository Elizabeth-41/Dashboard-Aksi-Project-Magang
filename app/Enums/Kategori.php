<?php

namespace App\Enums;

enum Kategori: string
{
    case Tik = 'tik';
    case NonTik = 'non_tik';

    public function label(): string
    {
        return match ($this) {
            self::Tik => 'TIK',
            self::NonTik => 'Non-TIK',
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
