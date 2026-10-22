<?php

namespace Tests\Feature\Admin;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;
use App\Notifications\UndanganAkses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class AllowedUserControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    private User $adminPusat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminPusat = $this->adminPusatUser();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/admin/allowed-users')->assertUnauthorized();
    }

    public function test_unit_is_forbidden(): void
    {
        $unit = $this->unitUser();

        $this->actingAs($unit)->getJson('/api/admin/allowed-users')->assertForbidden();
        $this->actingAs($unit)->postJson('/api/admin/allowed-users', [])->assertForbidden();
    }

    public function test_admin_kecamatan_role_is_no_longer_accepted(): void
    {
        $dinas = Dinas::factory()->create();

        $this->actingAs($this->adminPusat)->postJson('/api/admin/allowed-users', [
            'email' => 'camat@banjarmasinkota.go.id',
            'role' => 'admin_kecamatan',
            'dinas_id' => $dinas->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $allowed = AllowedUser::factory()->create();
        $this->actingAs($this->adminPusat)->patchJson("/api/admin/allowed-users/{$allowed->id}", [
            'role' => 'admin_kecamatan',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_index_lists_whitelist_with_dinas_and_last_login(): void
    {
        $dinas = Dinas::factory()->create(['nama' => 'DLH', 'slug' => 'dlh']);
        AllowedUser::factory()->create([
            'email' => 'dlh@banjarmasinkota.go.id',
            'dinas_id' => $dinas->id,
            'last_login_at' => now(),
        ]);

        $response = $this->actingAs($this->adminPusat)->getJson('/api/admin/allowed-users');

        $response->assertOk()->assertJsonCount(2, 'data');
        $entry = collect($response->json('data'))->firstWhere('email', 'dlh@banjarmasinkota.go.id');
        $this->assertSame('DLH', $entry['dinas']['nama']);
        $this->assertSame(AllowedUserStatus::Aktif->value, $entry['status']);
        $this->assertNotNull($entry['last_login_at']);
    }

    public function test_store_whitelists_email_creates_account_and_sends_invitation(): void
    {
        Notification::fake();
        $dinas = Dinas::factory()->create();

        $response = $this->actingAs($this->adminPusat)->postJson('/api/admin/allowed-users', [
            'email' => 'Dinkes@BanjarmasinKota.go.id',
            'name' => 'Operator Dinkes',
            'role' => 'unit',
            'dinas_id' => $dinas->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'dinkes@banjarmasinkota.go.id')
            ->assertJsonPath('data.role', 'unit')
            ->assertJsonPath('data.dinas.id', $dinas->id)
            ->assertJsonPath('data.invited_by', $this->adminPusat->id);

        $user = User::query()->where('email', 'dinkes@banjarmasinkota.go.id')->first();
        $this->assertNotNull($user);
        $this->assertSame('Operator Dinkes', $user->name);
        $this->assertSame(UserRole::Unit, $user->role);
        $this->assertSame($dinas->id, $user->dinas_id);
        Notification::assertSentTo($user, UndanganAkses::class, function (UndanganAkses $undangan) use ($user, $dinas) {
            $mail = $undangan->toMail($user);

            return $undangan->via($user) === ['mail']
                && $mail->subject === 'Undangan akses Dashboard Rencana Aksi Smart City Banjarmasin'
                && str_contains(implode(' ', $mail->introLines), $dinas->nama)
                && str_contains($mail->actionUrl, '/reset-password?token=')
                && str_contains($mail->actionUrl, urlencode($user->email));
        });
    }

    /**
     * Admin Pusat juga wajib terikat unit (penanda asal instansi), tetapi
     * cakupannya tetap seluruh unit karena ditentukan oleh role.
     */
    public function test_store_admin_pusat_with_dinas_keeps_full_scope(): void
    {
        Notification::fake();
        $diskominfotik = Dinas::factory()->create(['nama' => 'Diskominfotik', 'slug' => 'diskominfotik']);
        $unitLain = Dinas::factory()->create();

        $this->actingAs($this->adminPusat)->postJson('/api/admin/allowed-users', [
            'email' => 'kominfo@banjarmasinkota.go.id',
            'role' => 'admin_pusat',
            'dinas_id' => $diskominfotik->id,
        ])->assertCreated()->assertJsonPath('data.dinas_id', $diskominfotik->id);

        $akun = User::query()->where('email', 'kominfo@banjarmasinkota.go.id')->sole();
        $this->assertNull($akun->cakupanDinasIds(), 'Admin Pusat tetap mencakup semua unit.');
        $this->assertTrue($akun->mencakupDinas($unitLain->id));
    }

    public function test_store_requires_dinas_for_every_role(): void
    {
        foreach (['unit', 'admin_pusat'] as $role) {
            $this->actingAs($this->adminPusat)->postJson('/api/admin/allowed-users', [
                'email' => "{$role}@banjarmasinkota.go.id",
                'role' => $role,
            ])->assertUnprocessable()->assertJsonValidationErrors(['dinas_id']);
        }
    }

    public function test_store_rejects_duplicate_email_and_unknown_role(): void
    {
        AllowedUser::factory()->create(['email' => 'ada@banjarmasinkota.go.id']);

        $this->actingAs($this->adminPusat)->postJson('/api/admin/allowed-users', [
            'email' => 'ADA@banjarmasinkota.go.id',
            'role' => 'superadmin',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'role']);
    }

    public function test_update_role_and_dinas_is_propagated_to_account(): void
    {
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->untuk($user)->create();
        $dinasBaru = Dinas::factory()->create();

        $this->actingAs($this->adminPusat)->patchJson("/api/admin/allowed-users/{$allowed->id}", [
            'role' => 'admin_pusat',
            'dinas_id' => $dinasBaru->id,
        ])->assertOk()->assertJsonPath('data.role', 'admin_pusat');

        $this->assertSame(UserRole::AdminPusat, $user->fresh()->role);
        $this->assertSame($dinasBaru->id, $user->fresh()->dinas_id);
    }

    public function test_revoking_status_terminates_existing_sessions(): void
    {
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->untuk($user)->create();
        DB::table('sessions')->insert([
            'id' => 'sesi-unit',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->adminPusat)->patchJson("/api/admin/allowed-users/{$allowed->id}", [
            'status' => 'dicabut',
        ])->assertOk()->assertJsonPath('data.status', 'dicabut');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-unit']);
        $this->assertSame(AllowedUserStatus::Dicabut, $allowed->fresh()->status);
    }

    public function test_destroy_removes_whitelist_but_keeps_account(): void
    {
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->untuk($user)->create();

        $this->actingAs($this->adminPusat)->deleteJson("/api/admin/allowed-users/{$allowed->id}")->assertNoContent();

        $this->assertDatabaseMissing('allowed_users', ['id' => $allowed->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
