<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk setiap respons: anti-clickjacking, anti MIME-sniffing,
 * pembatasan referrer & fitur browser, HSTS (HTTPS), dan Content Security
 * Policy sebagai lapis pertahanan XSS.
 */
class TambahHeaderKeamanan
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $modeCsp = config('keamanan.csp');

        // Nonce harus disiapkan sebelum view dirender agar tag @vite memakainya.
        if ($modeCsp !== 'off') {
            Vite::useCspNonce();
        }

        $response = $next($request);

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.config('keamanan.hsts_max_age').'; includeSubDomains');
        }

        if ($modeCsp !== 'off' && $this->halamanHtmlTanpaCsp($response)) {
            $nama = $modeCsp === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $response->headers->set($nama, $this->kebijakanCsp());
        }

        return $response;
    }

    /**
     * CSP halaman hanya untuk dokumen HTML aplikasi. Respons lain (JSON, PDF,
     * gambar bukti) tidak dirender sebagai halaman — kebijakan halaman bisa
     * mengganggu penampil PDF bawaan browser — dan respons yang sudah membawa
     * CSP sendiri (mis. gambar bukti ber-sandbox) tidak boleh ditimpa.
     */
    protected function halamanHtmlTanpaCsp(Response $response): bool
    {
        return str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $response->headers->has('Content-Security-Policy');
    }

    /**
     * Skrip hanya dari origin sendiri atau yang ber-nonce. Gaya inline masih
     * diizinkan karena React/recharts memakai atribut style; risikonya jauh
     * lebih kecil daripada skrip inline.
     */
    protected function kebijakanCsp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".Vite::cspNonce()."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
