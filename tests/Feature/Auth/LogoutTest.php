<?php

namespace Tests\Feature\Auth;

use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_user_can_log_out(): void
    {
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->create();

        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil');

        $this->assertGuest();
    }

    public function test_current_user_endpoint_returns_profile_with_dinas(): void
    {
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.dinas.id', $user->dinas_id)
            ->assertJsonMissingPath('data.two_factor_secret');
    }

    public function test_guest_gets_401_from_current_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
