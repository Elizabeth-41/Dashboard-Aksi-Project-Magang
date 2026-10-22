<?php

namespace Database\Factories;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllowedUser>
 */
class AllowedUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Unit,
            'dinas_id' => Dinas::factory(),
            'status' => AllowedUserStatus::Aktif,
            'invited_by' => User::factory()->adminPusat(),
            'invited_at' => now(),
            'last_login_at' => null,
        ];
    }

    public function adminPusat(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AdminPusat,
            'dinas_id' => null,
        ]);
    }

    public function dicabut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AllowedUserStatus::Dicabut,
        ]);
    }

    /**
     * Whitelist untuk user yang sudah ada (email & role & dinas mengikuti user).
     */
    public function untuk(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $user->email,
            'role' => $user->role,
            'dinas_id' => $user->dinas_id,
        ]);
    }
}
