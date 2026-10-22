<?php

namespace Tests\Feature\Keamanan;

use App\Models\AllowedUser;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Pertahanan brute force: setiap endpoint yang menerima rahasia atau mahal
 * diproses harus berhenti menerima percobaan setelah batasnya (HTTP 429).
 */
class PembatasPercobaanTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_login_is_limited_per_ip_across_many_emails(): void
    {
        // Credential stuffing: tiap email hanya dicoba sekali, tapi dari satu IP.
        foreach (range(1, 20) as $i) {
            $this->postJson('/api/login', ['email' => "orang{$i}@contoh.test", 'password' => 'salah'])->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'orang21@contoh.test', 'password' => 'salah'])->assertTooManyRequests();
    }

    public function test_forgot_password_is_limited(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/forgot-password', ['email' => 'siapa@contoh.test'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'siapa@contoh.test'])->assertTooManyRequests();
    }

    public function test_reset_password_token_guessing_is_limited(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/reset-password', [
                'token' => "tebakan-{$i}",
                'email' => 'korban@contoh.test',
                'password' => 'KataSandiBaru123',
                'password_confirmation' => 'KataSandiBaru123',
            ])->assertStatus(422);
        }

        $this->postJson('/api/reset-password', [
            'token' => 'tebakan-4',
            'email' => 'korban@contoh.test',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertTooManyRequests();
    }

    public function test_confirm_password_is_limited_for_a_logged_in_session(): void
    {
        $user = $this->unitUser();

        foreach (range(1, 5) as $i) {
            $this->actingAs($user, 'web')->postJson('/api/user/confirm-password', ['password' => "tebakan-{$i}"])->assertStatus(422);
        }

        $this->actingAs($user, 'web')->postJson('/api/user/confirm-password', ['password' => 'password'])->assertTooManyRequests();
    }

    public function test_two_factor_setup_code_guessing_is_limited(): void
    {
        $user = $this->unitUser();
        $this->actingAs($user, 'web')->withSession(['auth.password_confirmed_at' => time()]);
        $this->actingAs($user, 'web')->postJson('/api/user/two-factor-authentication')->assertSuccessful();

        // Sudah 1 dari 5 kuota "sensitif" terpakai oleh permintaan di atas.
        foreach (range(1, 4) as $i) {
            $this->actingAs($user, 'web')->postJson('/api/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertStatus(422);
        }

        $this->actingAs($user, 'web')->postJson('/api/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertTooManyRequests();
    }

    public function test_api_routes_have_a_general_limit(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(2));

        $this->getJson('/api/dinas')->assertOk();
        $this->getJson('/api/dinas')->assertOk();
        $this->getJson('/api/dinas')->assertTooManyRequests();
    }

    public function test_report_export_is_limited(): void
    {
        RateLimiter::for('ekspor', fn () => Limit::perMinute(1));
        $user = $this->unitUser();

        $this->actingAs($user)->get('/api/laporan/export?format=xlsx')->assertOk();
        $this->actingAs($user)->getJson('/api/laporan/export?format=xlsx')->assertTooManyRequests();
    }

    public function test_upload_is_limited(): void
    {
        RateLimiter::for('unggah', fn () => Limit::perMinute(1));
        $user = $this->unitUser();
        $rencana = $user->dinas->rencanaAksi()->create(RencanaAksi::factory()->raw(['dinas_id' => $user->dinas_id]));

        // Permintaan pertama lolos pembatas (ditolak validasi karena tanpa file).
        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [])->assertStatus(422);
        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [])->assertTooManyRequests();
    }

    public function test_revoked_or_unknown_accounts_do_not_bypass_login_limit(): void
    {
        $user = User::factory()->create(['email' => 'target@contoh.test']);
        AllowedUser::factory()->untuk($user)->create();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/login', ['email' => 'target@contoh.test', 'password' => "tebakan-{$i}"])->assertStatus(422);
        }

        // Password benar pun ditolak selama masa tunggu.
        $this->postJson('/api/login', ['email' => 'target@contoh.test', 'password' => 'password'])->assertTooManyRequests();
    }
}
