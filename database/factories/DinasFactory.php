<?php

namespace Database\Factories;

use App\Models\Dinas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dinas>
 */
class DinasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = 'Dinas '.fake()->unique()->words(2, true);

        return [
            'nama' => $nama,
            'slug' => Str::slug($nama),
        ];
    }

}
