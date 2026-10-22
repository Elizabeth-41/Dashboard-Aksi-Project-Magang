<?php

namespace Database\Seeders;

use App\Models\Dinas;
use Database\Seeders\Concerns\ReadsCsv;
use Illuminate\Database\Seeder;

/**
 * 39 unit (leading sector) dari database/seed-data/dinas-unit-seed-data.csv.
 */
class DinasSeeder extends Seeder
{
    use ReadsCsv;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->readCsv('database/seed-data/dinas-unit-seed-data.csv') as $row) {
            Dinas::query()->updateOrCreate(
                ['slug' => trim($row['slug'])],
                ['nama' => trim($row['nama'])],
            );
        }
    }
}
