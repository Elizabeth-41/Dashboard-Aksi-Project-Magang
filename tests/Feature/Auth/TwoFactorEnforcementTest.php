<?php

namespace Tests\Feature\Auth;

use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_admin_pusat_without_two_factor_is_blocked_from_protected_endpoints(): void
    {
        $admin = $this->adminPusatTanpaDuaFaktor();
        $rencana = RencanaAksi::factory()->create();

        foreach ([
            ['get', '/api/admin/allowed-users'],
            ['get', '/api/admin/activity-log'],
            ['get', '/api/verifikasi/antrean'],
            ['get', '/api/laporan'],
            ['get', '/api/notifikasi'],
        ] as [$method, $uri]) {
            $this->actingAs($admin)->{$method.'Json'}($uri)
                ->assertForbidden()
                ->assertJsonPath('code', 'two_factor_required');
        }

        $this->actingAs($admin)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'selesai'])
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
        $this->assertSame('belum_mulai', $rencana->fresh()->status->value);
    }

    public function test_admin_pusat_without_two_factor_can_still_read_own_identity(): void
    {
        $admin = $this->adminPusatTanpaDuaFaktor();

        $this->actingAs($admin)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonPath('data.two_factor_required', true);
    }

    public function test_admin_pusat_with_confirmed_two_factor_passes(): void
    {
        $admin = $this->adminPusatUser();

        $this->actingAs($admin)->getJson('/api/admin/allowed-users')->assertOk();
        $this->actingAs($admin)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_unconfirmed_two_factor_does_not_count_as_enabled(): void
    {
        $admin = $this->adminPusatTanpaDuaFaktor();
        $admin->forceFill(['two_factor_secret' => encrypt('rahasia'), 'two_factor_confirmed_at' => null])->save();

        $this->actingAs($admin)->getJson('/api/admin/allowed-users')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
    }

    public function test_two_factor_is_optional_for_unit(): void
    {
        $unit = $this->unitUser();

        $this->actingAs($unit)->getJson('/api/notifikasi')->assertOk();
        $this->actingAs($unit)->getJson('/api/user')->assertJsonPath('data.two_factor_required', false);
    }
}
