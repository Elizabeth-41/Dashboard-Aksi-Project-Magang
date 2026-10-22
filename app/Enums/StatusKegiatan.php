<?php

namespace App\Enums;

/**
 * Status kegiatan selalu diubah manual oleh Unit/Admin, tidak pernah
 * dihitung otomatis dari bukti.
 */
enum StatusKegiatan: string
{
    case BelumMulai = 'belum_mulai';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::BelumMulai => 'Belum Mulai',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
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
