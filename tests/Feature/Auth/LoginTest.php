<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_whitelisted_user_with_valid_password_logs_in_and_gets_login_berhasil(): void
    {
        $user = User::factory()->create(['email' => 'unit@banjarmasinkota.go.id']);
        AllowedUser::factory()->untuk($user)->create();

        $response = $this->postJson('/api/login', [
            'email' => 'unit@banjarmasinkota.go.id',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login Berhasil')
            ->assertJsonPath('two_factor', false)
            ->assertJsonPath('user.email', 'unit@banjarmasinkota.go.id')
            ->assertJsonPath('user.role', UserRole::Unit->value)
            ->assertJsonMissingPath('user.password');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Akun ber-2FA belum login setelah password benar: Fortify menjawab
     * 200 + two_factor=true, dan SPA harus menampilkan tahap kode OTP.
     */
    public function test_two_factor_account_is_not_logged_in_until_challenge_is_answered(): void
    {
        $user = User::factory()->denganDuaFaktor()->create(['email' => 'admin@banjarmasinkota.go.id']);
        AllowedUser::factory()->untuk($user)->create();

        $this->postJson('/api/login', [
            'email' => 'admin@banjarmasinkota.go.id',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('two_factor', true);

        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_records_last_login_and_syncs_role_from_whitelist(): void
    {
        $dinas = Dinas::factory()->create();
        $user = User::factory()->create();
        $allowed = AllowedUser::factory()->create([
            'email' => $user->email,
            'role' => UserRole::AdminPusat,
            'dinas_id' => $dinas->id,
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertNotNull($allowed->fresh()->last_login_at);
        $this->assertSame(UserRole::AdminPusat, $user->fresh()->role);
        $this->assertSame($dinas->id, $user->fresh()->dinas_id);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        $user = User::factory()->create(['email' => 'unit@banjarmasinkota.go.id']);
        AllowedUser::factory()->untuk($user)->create();

        $this->postJson('/api/login', [
            'email' => 'Unit@BanjarmasinKota.go.id',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_not_in_whitelist_is_rejected_with_login_gagal(): void
    {
        User::factory()->create(['email' => 'tidak@terdaftar.go.id']);

        $response = $this->postJson('/api/login', [
            'email' => 'tidak@terdaftar.go.id',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', fn (string $message) => str_starts_with($message, 'Login gagal'));
        $this->assertGuest();
    }

    public function test_revoked_whitelist_entry_is_rejected(): void
    {
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->dicabut()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_with_same_message_as_unknown_email(): void
    {
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->create();

        $wrongPassword = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'salah']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'x@y.go.id', 'password' => 'salah']);

        $wrongPassword->assertUnprocessable();
        $this->assertSame($wrongPassword->json('errors.email.0'), $unknownEmail->json('errors.email.0'));
        $this->assertGuest();
    }

    public function test_whitelisted_email_without_account_is_rejected(): void
    {
        AllowedUser::factory()->create(['email' => 'belum-punya-akun@banjarmasinkota.go.id']);

        $this->postJson('/api/login', [
            'email' => 'belum-punya-akun@banjarmasinkota.go.id',
            'password' => 'password',
        ])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'brute@force.id', 'password' => 'salah'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'brute@force.id', 'password' => 'salah'])
            ->assertTooManyRequests();
    }
}
