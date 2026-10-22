<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute autentikasi milik Fortify didaftarkan oleh paketnya sendiri, dan
 * Fortify hanya menyediakan pembatas untuk login & tantangan 2FA. Middleware
 * ini menambahkan pembatas percobaan ke rute Fortify lain yang menerima
 * rahasia (password, token reset, kode OTP), berdasarkan nama rutenya.
 */
class BatasiRuteAutentikasi
{
    /**
     * Nama rute Fortify => nama limiter (lihat FortifyServiceProvider).
     *
     * @var array<string, string>
     */
    public const PETA = [
        'password.email' => 'lupa-password',
        'password.update' => 'lupa-password',
        'password.confirm.store' => 'sensitif',
        'user-password.update' => 'sensitif',
        'two-factor.confirm' => 'sensitif',
        'two-factor.enable' => 'sensitif',
        'two-factor.disable' => 'sensitif',
        'two-factor.regenerate-recovery-codes' => 'sensitif',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::PETA[$request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
