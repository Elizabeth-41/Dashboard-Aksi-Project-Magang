<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->konfigurasiPembatasPercobaan();

        // Berlaku saat password dibuat/diubah (undangan, reset, ganti password).
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());
    }

    /**
     * Pembatas percobaan untuk API aplikasi. Limiter autentikasi (login, 2FA,
     * lupa password) ada di FortifyServiceProvider.
     */
    protected function konfigurasiPembatasPercobaan(): void
    {
        // Batas umum semua endpoint API: cukup longgar untuk dashboard yang
        // memuat beberapa permintaan sekaligus, cukup ketat untuk scraping/DoS.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('unggah', function (Request $request) {
            return Limit::perMinute(20)->by('unggah|'.($request->user()?->id ?: $request->ip()));
        });

        // Membuat PDF/Excel berat di server.
        RateLimiter::for('ekspor', function (Request $request) {
            return Limit::perMinute(10)->by('ekspor|'.($request->user()?->id ?: $request->ip()));
        });
    }
}
