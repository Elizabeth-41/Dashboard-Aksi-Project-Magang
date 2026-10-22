<?php

namespace Tests\Feature\Auth;

use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevokedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_active_session_is_logged_out_once_whitelist_is_revoked(): void
    {
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->untuk($user)->create();

        $this->actingAs($user)->getJson('/api/user')->assertOk();

        $allowed->update(['status' => 'dicabut']);

        $this->actingAs($user)->getJson('/api/user')->assertForbidden();
        $this->assertGuest();
    }

    public function test_user_whose_whitelist_entry_was_deleted_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/user')->assertForbidden();
    }
}
