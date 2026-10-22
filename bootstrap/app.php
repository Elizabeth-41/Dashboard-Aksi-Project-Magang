<?php

use App\Http\Middleware\BatasiRuteAutentikasi;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsWhitelisted;
use App\Http\Middleware\TambahHeaderKeamanan;
use App\Support\BatasUnggah;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA React memakai sesi cookie (Sanctum), bukan token.
        $middleware->statefulApi();

        // Keamanan: header (CSP dll.) untuk semua respons, batas umum API, dan
        // batas percobaan untuk rute autentikasi Fortify yang menerima rahasia.
        $middleware->append(TambahHeaderKeamanan::class);
        $middleware->throttleApi('api');
        $middleware->appendToGroup('web', BatasiRuteAutentikasi::class);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'whitelisted' => EnsureUserIsWhitelisted::class,
            '2fa' => EnsureTwoFactorIsEnabled::class,
        ]);

        // Fortify hanya mendaftarkan rute POST `login.store`; tanpa ini permintaan
        // non-JSON dari tamu (mis. unduhan laporan lewat navigasi browser) gagal
        // dengan "Route [login] not defined" alih-alih diarahkan ke halaman SPA.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // File melebihi post_max_size PHP: isi permintaan dibuang sebelum sampai
        // validasi, jadi alasannya dijelaskan di sini dalam format galat validasi
        // agar tampil di bawah kolom file.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $pesan = 'Ukuran file melebihi batas '.BatasUnggah::label().'. Perkecil atau kompres file lalu coba lagi.';

            return response()->json(['message' => $pesan, 'errors' => ['file' => [$pesan]]], 413);
        });
    })->create();
