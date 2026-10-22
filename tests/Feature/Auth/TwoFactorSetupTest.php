<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Jalur keluar bagi Admin Pusat yang diblokir middleware `2fa`: rute Fortify
 * untuk memasang 2FA harus tetap terjangkau, dan setelah dikonfirmasi akses
 * penuh langsung terbuka.
 */
class TwoFactorSetupTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_blocked_admin_pusat_can_enable_and_confirm_two_factor_then_regains_access(): void
    {
        $admin = $this->adminPusatTanpaDuaFaktor();

        $this->actingAs($admin)->getJson('/api/admin/allowed-users')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');

        $this->pasangDuaFaktor($admin)->assertSuccessful();

        // QR & kunci manual dipakai halaman pemasangan di SPA.
        $this->actingAs($admin, 'web')->getJson('/api/user/two-factor-qr-code')
            ->assertSuccessful()
            ->assertJsonStructure(['svg']);
        $this->actingAs($admin, 'web')->getJson('/api/user/two-factor-secret-key')
            ->assertSuccessful()
            ->assertJsonStructure(['secretKey']);

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at, 'Belum dikonfirmasi, jadi belum boleh dianggap aktif.');
        $this->actingAs($admin)->getJson('/api/admin/allowed-users')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');

        $this->konfirmasiDuaFaktor($admin)->assertSuccessful();

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
        $this->actingAs($admin->fresh(), 'web')->getJson('/api/user/two-factor-recovery-codes')
            ->assertSuccessful()
            ->assertJsonCount(8);
        $this->actingAs($admin->fresh())->getJson('/api/admin/allowed-users')->assertOk();
        $this->actingAs($admin->fresh())->getJson('/api/user')->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_unit_may_enable_two_factor_voluntarily(): void
    {
        $unit = $this->unitUser();

        $this->actingAs($unit)->getJson('/api/notifikasi')->assertOk();

        $this->pasangDuaFaktor($unit)->assertSuccessful();
        $this->konfirmasiDuaFaktor($unit)->assertSuccessful();

        $this->assertTrue($unit->fresh()->hasTwoFactorEnabled());
        $this->actingAs($unit->fresh())->getJson('/api/notifikasi')->assertOk();
    }

    /**
     * Rute Fortify memakai guard `web` (bukan sanctum), jadi guard-nya disebut
     * eksplisit; memasang 2FA juga menuntut konfirmasi password lebih dulu.
     */
    private function pasangDuaFaktor(User $user): TestResponse
    {
        $this->actingAs($user, 'web')
            ->postJson('/api/user/confirm-password', ['password' => 'password'])
            ->assertSuccessful();

        return $this->actingAs($user, 'web')->postJson('/api/user/two-factor-authentication');
    }

    private function konfirmasiDuaFaktor(User $user): TestResponse
    {
        $secret = decrypt($user->fresh()->two_factor_secret);

        return $this->actingAs($user, 'web')->postJson('/api/user/confirmed-two-factor-authentication', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);
    }
}
