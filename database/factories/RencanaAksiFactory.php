<?php

namespace Database\Factories;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RencanaAksi>
 */
class RencanaAksiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = fake()->numberBetween(2019, 2025);
        $selesai = fake()->numberBetween($mulai, 2026);
        $tahun = range($mulai, $selesai);

        return [
            'dinas_id' => Dinas::factory(),
            'program' => fake()->optional()->sentence(6),
            'judul' => 'Pengembangan '.fake()->unique()->sentence(4),
            'jenis_keluaran' => fake()->randomElement(JenisKeluaran::cases()),
            'kategori' => fake()->randomElement(Kategori::cases()),
            'dimensi' => fake()->randomElement(Dimensi::cases()),
            'tahun_label' => $mulai === $selesai ? (string) $mulai : "{$mulai} - {$selesai}",
            'tahun_pelaksanaan' => $tahun,
            'status' => StatusKegiatan::BelumMulai,
            'created_by' => null,
        ];
    }

    /**
     * Periode tahun tertentu (boleh tidak kontinu, mis. [2023, 2025]).
     *
     * @param  array<int, int>  $tahun
     */
    public function tahun(array $tahun): static
    {
        sort($tahun);

        return $this->state(fn (array $attributes) => [
            'tahun_label' => count($tahun) === 1 ? (string) $tahun[0] : implode(' - ', [min($tahun), max($tahun)]),
            'tahun_pelaksanaan' => array_values($tahun),
        ]);
    }

    public function berjalan(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusKegiatan::Berjalan,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusKegiatan::Selesai,
        ]);
    }
    /**
     * Kegiatan yang buktinya tidak perlu ditinjau admin.
     */
    public function tanpaVerifikasi(): static
    {
        return $this->state(fn (array $attributes) => ['perlu_verifikasi' => false]);
    }

}
