<?php

namespace Tests\Feature\Models;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllowedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_defaults_to_aktif_and_role_is_cast_to_enum(): void
    {
        $allowed = AllowedUser::factory()->adminPusat()->create();

        $fresh = $allowed->fresh();

        $this->assertSame(AllowedUserStatus::Aktif, $fresh->status);
        $this->assertSame(UserRole::AdminPusat, $fresh->role);
        $this->assertNull($fresh->dinas_id);
        $this->assertTrue($fresh->isAktif());
    }

    public function test_aktif_scope_excludes_revoked_entries(): void
    {
        $aktif = AllowedUser::factory()->create();
        AllowedUser::factory()->dicabut()->create();

        $this->assertSame([$aktif->id], AllowedUser::aktif()->pluck('id')->all());
    }

    public function test_email_must_be_unique(): void
    {
        AllowedUser::factory()->create(['email' => 'unit@banjarmasinkota.go.id']);

        $this->expectException(QueryException::class);

        AllowedUser::factory()->create(['email' => 'unit@banjarmasinkota.go.id']);
    }

    public function test_user_relation_matches_account_by_email(): void
    {
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->untuk($user)->create();

        $this->assertTrue($allowed->user->is($user));
        $this->assertSame($user->dinas_id, $allowed->dinas_id);
    }

    public function test_deleting_inviter_keeps_whitelist_entry(): void
    {
        $inviter = User::factory()->adminPusat()->create();
        $allowed = AllowedUser::factory()->create(['invited_by' => $inviter->id]);

        $inviter->delete();

        $this->assertNull($allowed->fresh()->invited_by);
    }
}
