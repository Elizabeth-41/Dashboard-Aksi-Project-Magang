<?php

namespace App\Http\Middleware;

use App\Models\AllowedUser;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akses yang dicabut di Manajemen Akses harus langsung berlaku, meski user
 * masih memegang sesi login: sesi dimatikan dan permintaan ditolak.
 */
class EnsureUserIsWhitelisted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $isWhitelisted = AllowedUser::query()
            ->aktif()
            ->where('email', $user->email)
            ->exists();

        if ($isWhitelisted) {
            return $next($request);
        }

        $guard = Auth::guard('web');

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Guard sanctum meng-cache user yang sudah ter-resolve; buang agar
        // sisa request ini pun benar-benar dianggap tamu.
        Auth::forgetGuards();

        abort(403, 'Akses Anda telah dicabut.');
    }
}
