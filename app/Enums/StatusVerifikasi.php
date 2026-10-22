<?php

namespace App\Enums;

/**
 * Status verifikasi bukti dukung. Hanya "diterima" yang tampil ke Guest.
 */
enum StatusVerifikasi: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
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
