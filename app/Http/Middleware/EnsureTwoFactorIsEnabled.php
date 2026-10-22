<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA wajib untuk Admin Pusat (opsional untuk role lain). Selama belum
 * diaktifkan, akunnya hanya boleh membaca identitasnya sendiri dan memakai
 * rute Fortify untuk memasang 2FA; semua aksi lain ditolak.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->role->requiresTwoFactor() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Kode mesin dipakai SPA untuk mengarahkan ke halaman pemasangan 2FA,
        // supaya bisa dibedakan dari 403 karena cakupan/role.
        return response()->json([
            'message' => 'Aktifkan autentikasi dua faktor terlebih dahulu untuk memakai akun Admin Pusat.',
            'code' => 'two_factor_required',
        ], Response::HTTP_FORBIDDEN);
    }
}
