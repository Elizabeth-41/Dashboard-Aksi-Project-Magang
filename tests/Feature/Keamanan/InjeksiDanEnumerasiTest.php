<?php

namespace Tests\Feature\Keamanan;

use App\Models\AllowedUser;
use App\Models\BuktiDukung;
use App\Models\User;
use App\Notifications\BuktiBaruMenungguVerifikasi;
use App\Notifications\BuktiDitolak;
use App\Support\TeksEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class InjeksiDanEnumerasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_text_cannot_inject_links_into_admin_emails(): void
    {
        $bukti = BuktiDukung::factory()->create([
            'nama_file' => '[unduh](https://evil.example/a).pdf',
            'deskripsi' => "Mohon segera ke [portal verifikasi](https://evil.example/login)\n# Judul palsu\n<b>tebal</b>",
        ]);
        $admin = User::factory()->adminPusat()->create();

        $html = (string) (new BuktiBaruMenungguVerifikasi($bukti))->toMail($admin)->render();

        $this->assertDoesNotMatchRegularExpression('~<a[^>]+href="https://evil\.example~', $html, 'Tautan dari isian pengguna tidak boleh bisa diklik.');
        $this->assertStringNotContainsString('<h1>Judul palsu', $html);
        $this->assertStringNotContainsString('<b>tebal</b>', $html);
        // Teksnya tetap terbaca utuh oleh admin.
        $this->assertStringContainsString('portal verifikasi', $html);
        $this->assertStringContainsString('https://evil.example/login', $html);
    }

    public function test_rejection_reason_cannot_inject_links_either(): void
    {
        $bukti = BuktiDukung::factory()->ditolak('Lihat [panduan](https://evil.example)')->create();
        $unit = User::factory()->create();

        $html = (string) (new BuktiDitolak($bukti))->toMail($unit)->render();

        $this->assertDoesNotMatchRegularExpression('~<a[^>]+href="https://evil\.example~', $html);
    }

    public function test_escaped_text_still_reads_normally(): void
    {
        $this->assertSame('laporan\\.pdf', TeksEmail::aman('laporan.pdf'));
        $this->assertSame('baris satu baris dua', TeksEmail::aman("baris satu\n\nbaris dua"));
        $this->assertSame('', TeksEmail::aman(null));
    }

    public function test_forgot_password_does_not_reveal_which_emails_exist(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@contoh.test']);
        AllowedUser::factory()->untuk($user)->create();

        $terdaftar = $this->postJson('/api/forgot-password', ['email' => 'ada@contoh.test']);
        $tidakAda = $this->postJson('/api/forgot-password', ['email' => 'tidak-ada@contoh.test']);

        $terdaftar->assertOk();
        $tidakAda->assertOk();
        $this->assertSame($terdaftar->json(), $tidakAda->json(), 'Jawaban harus identik agar email tak bisa ditebak.');
        $this->assertStringNotContainsString('tidak menemukan', (string) $tidakAda->getContent());
    }

    public function test_weak_passwords_are_rejected_when_setting_a_password(): void
    {
        // Yang diuji di sini kebijakan password, bukan pembatas percobaan.
        RateLimiter::for('lupa-password', fn () => Limit::none());
        $user = User::factory()->create(['email' => 'baru@contoh.test']);
        $token = Password::broker()->createToken($user);

        foreach (['password', 'password12', 'semuakecil123', 'SEMUABESAR123', 'TanpaAngkaSama'] as $lemah) {
            $this->postJson('/api/reset-password', [
                'token' => $token,
                'email' => 'baru@contoh.test',
                'password' => $lemah,
                'password_confirmation' => $lemah,
            ])->assertStatus(422)->assertJsonValidationErrors(['password']);
        }

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'baru@contoh.test',
            'password' => 'KataSandiKuat2026',
            'password_confirmation' => 'KataSandiKuat2026',
        ])->assertOk();
    }
}
