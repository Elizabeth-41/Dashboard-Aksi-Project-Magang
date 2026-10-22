<?php

namespace Database\Factories;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BuktiDukung>
 */
class BuktiDukungFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ekstensi = fake()->randomElement(['pdf', 'jpg', 'png', 'docx', 'xlsx']);
        $namaFile = Str::slug(fake()->words(3, true)).'.'.$ekstensi;

        return [
            'rencana_aksi_id' => RencanaAksi::factory(),
            'nama_file' => $namaFile,
            'path_file' => 'bukti/'.Str::uuid().'.'.$ekstensi,
            'tipe_file' => match ($ekstensi) {
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
            'ukuran_file' => fake()->numberBetween(10_000, 5_000_000),
            'deskripsi' => fake()->sentence(10),
            'uploaded_by' => User::factory(),
            'status_verifikasi' => StatusVerifikasi::Menunggu,
            'alasan_penolakan' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function diterima(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_verifikasi' => StatusVerifikasi::Diterima,
            'alasan_penolakan' => null,
            'verified_by' => User::factory()->adminPusat(),
            'verified_at' => now(),
        ]);
    }

    public function ditolak(?string $alasan = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status_verifikasi' => StatusVerifikasi::Ditolak,
            'alasan_penolakan' => $alasan ?? fake()->sentence(8),
            'verified_by' => User::factory()->adminPusat(),
            'verified_at' => now(),
        ]);
    }
}
