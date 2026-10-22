<?php

namespace App\Enums;

/**
 * Jenis keluaran rencana aksi (kolom "RENCANA AKSI" di Excel asli).
 */
enum JenisKeluaran: string
{
    case Aplikasi = 'aplikasi';
    case Literasi = 'literasi';
    case Infrastruktur = 'infrastruktur';
    case Sdm = 'sdm';
    case Kebijakan = 'kebijakan';

    public function label(): string
    {
        return match ($this) {
            self::Aplikasi => 'Aplikasi',
            self::Literasi => 'Literasi',
            self::Infrastruktur => 'Infrastruktur',
            self::Sdm => 'SDM',
            self::Kebijakan => 'Kebijakan',
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
