<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_cast_to_enum_and_defaults_to_unit(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Unit, $user->fresh()->role);
        $this->assertTrue($user->isUnit());
    }

    public function test_unit_scope_covers_only_its_own_dinas(): void
    {
        $dinas = Dinas::factory()->create();
        Dinas::factory()->create();
        $user = User::factory()->unit($dinas)->create();

        $this->assertSame([$dinas->id], $user->cakupanDinasIds());
    }

    public function test_admin_pusat_scope_is_unrestricted(): void
    {
        $admin = User::factory()->adminPusat()->create();

        $this->assertNull($admin->dinas_id);
        $this->assertNull($admin->cakupanDinasIds());
        $this->assertTrue($admin->isAdminPusat());
    }

    public function test_two_factor_columns_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => 'rahasia',
            'two_factor_recovery_codes' => 'kode',
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $serialized);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $serialized);
        $this->assertArrayNotHasKey('password', $serialized);
    }

    public function test_two_factor_counts_as_enabled_only_after_confirmation(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'rahasia']);

        $this->assertFalse($user->hasTwoFactorEnabled());

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    public function test_deleting_dinas_leaves_user_without_dinas(): void
    {
        $dinas = Dinas::factory()->create();
        $user = User::factory()->unit($dinas)->create();

        $dinas->delete();

        $this->assertNull($user->fresh()->dinas_id);
    }
}
