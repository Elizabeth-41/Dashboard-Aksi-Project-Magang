<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Unit,
            'dinas_id' => Dinas::factory(),
        ];
    }

    /**
     * Akun Unit/OPD untuk dinas tertentu.
     */
    public function unit(Dinas $dinas): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Unit,
            'dinas_id' => $dinas->id,
        ]);
    }

    /**
     * Admin Pusat (Diskominfotik): tidak terikat dinas.
     */
    public function adminPusat(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AdminPusat,
            'dinas_id' => null,
        ]);
    }

    /**
     * Akun dengan 2FA sudah dipasang dan dikonfirmasi.
     */
    public function denganDuaFaktor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(Str::random(16)),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all())),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
