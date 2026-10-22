<?php

namespace Database\Seeders;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Database\Seeders\Concerns\ReadsCsv;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 156 kegiatan dari database/seed-data/rencana-aksi-seed-data.csv (hasil normalisasi Excel
 * Google Data Studio 2019-2026). Status dibiarkan default "belum_mulai"
 * karena status selalu diisi manual oleh unit/admin.
 */
class RencanaAksiSeeder extends Seeder
{
    use ReadsCsv;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dinasIdBySlug = Dinas::query()->pluck('id', 'slug');

        foreach ($this->readCsv('database/seed-data/rencana-aksi-seed-data.csv') as $index => $row) {
            $slug = trim($row['leading_sector_slug']);
            $dinasId = $dinasIdBySlug->get($slug)
                ?? throw new RuntimeException('Baris '.($index + 2).": unit '{$slug}' belum ada — jalankan DinasSeeder dulu.");

            $tahun = json_decode($row['tahun_pelaksanaan'], true, flags: JSON_THROW_ON_ERROR);

            RencanaAksi::query()->updateOrCreate(
                [
                    'dinas_id' => $dinasId,
                    'dimensi' => Dimensi::from(trim($row['dimensi'])),
                    'judul' => trim($row['judul']),
                ],
                [
                    'program' => trim($row['program']) !== '' ? trim($row['program']) : null,
                    'jenis_keluaran' => JenisKeluaran::from(trim($row['jenis_keluaran'])),
                    'kategori' => Kategori::from(trim($row['kategori'])),
                    'tahun_label' => trim($row['tahun_label']),
                    'tahun_pelaksanaan' => array_map('intval', $tahun),
                ],
            );
        }
    }
}
