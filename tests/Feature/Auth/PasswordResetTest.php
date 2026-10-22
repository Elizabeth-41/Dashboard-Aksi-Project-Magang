<?php

namespace Tests\Feature\Auth;

use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_link_pointing_to_frontend_page(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://smartcity.banjarmasinkota.go.id/']);
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://smartcity.banjarmasinkota.go.id/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_valid_token_resets_password_and_allows_login(): void
    {
        $user = User::factory()->create();
        AllowedUser::factory()->untuk($user)->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'KataSandiBaru123!',
            'password_confirmation' => 'KataSandiBaru123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('KataSandiBaru123!', $user->fresh()->password));
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'KataSandiBaru123!'])->assertOk();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => 'token-palsu',
            'email' => $user->email,
            'password' => 'KataSandiBaru123!',
            'password_confirmation' => 'KataSandiBaru123!',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
