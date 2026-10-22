<?php

namespace App\Support;

use App\Models\RencanaAksi;

/**
 * Rentang tahun yang benar-benar dipakai kegiatan. Menggantikan batas tetap
 * 2019-2026 supaya kegiatan periode baru (mis. 2027-2031) ikut muncul di
 * filter tahun dan laporan.
 */
class RentangTahun
{
    /** Awal data historis Rencana Aksi Kota Cerdas. */
    public const AWAL_BAKU = 2019;

    /**
     * @return array{awal: int, akhir: int}
     */
    public function hitung(): array
    {
        // Kolom JSON; jumlah kegiatan ratusan, jadi cukup dihitung di PHP.
        $tahun = RencanaAksi::query()
            ->pluck('tahun_pelaksanaan')
            ->flatten()
            ->map(fn ($t) => (int) $t)
            ->filter();

        return [
            'awal' => min($tahun->min() ?? self::AWAL_BAKU, self::AWAL_BAKU),
            'akhir' => max($tahun->max() ?? (int) now()->year, self::AWAL_BAKU),
        ];
    }
}
