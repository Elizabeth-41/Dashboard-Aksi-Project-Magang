<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\AllowedUser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        // Cegah enumerasi akun: jawaban lupa-password selalu sama.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(fn (Request $request) => $this->authenticateWhitelistedUser($request));

        // Tanpa view Fortify, tautan reset password harus mengarah ke halaman SPA.
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        // Dua lapis: per email+IP (tebak password satu akun) dan per IP saja
        // (credential stuffing: banyak email dicoba dari satu sumber).
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(20)->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Lupa/reset password: cegah banjir email dan tebakan token reset.
        RateLimiter::for('lupa-password', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            return [
                Limit::perMinute(3)->by('lupa|'.$email.'|'.$request->ip()),
                Limit::perHour(20)->by('lupa-ip|'.$request->ip()),
            ];
        });

        // Aksi yang memverifikasi rahasia milik user yang sudah login (password
        // saat ini, kode OTP saat memasang 2FA): cegah tebakan dari sesi curian.
        RateLimiter::for('sensitif', function (Request $request) {
            return Limit::perMinute(5)->by('sensitif|'.($request->user()?->id ?? $request->ip()));
        });
    }

    /**
     * Login hanya berhasil jika email ada di whitelist (allowed_users) dengan
     * status aktif DAN password akun cocok. Keduanya gagal dengan pesan yang
     * sama agar tidak membocorkan email mana yang terdaftar.
     */
    protected function authenticateWhitelistedUser(Request $request): ?User
    {
        $email = Str::lower((string) $request->input(Fortify::username()));

        $isWhitelisted = AllowedUser::query()
            ->aktif()
            ->where('email', $email)
            ->exists();

        if (! $isWhitelisted) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            return null;
        }

        return $user;
    }
}
