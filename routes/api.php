<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AllowedUserController;
use App\Http\Controllers\Api\Admin\RencanaAksiController as AdminRencanaAksiController;
use App\Http\Controllers\Api\AuthenticatedUserController;
use App\Http\Controllers\Api\BuktiDukungController;
use App\Http\Controllers\Api\BuktiSearchController;
use App\Http\Controllers\Api\BuktiVerifikasiController;
use App\Http\Controllers\Api\DinasController;
use App\Http\Controllers\Api\DinasRencanaAksiController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\RencanaAksiBuktiController;
use App\Http\Controllers\Api\RencanaAksiController;
use App\Http\Controllers\Api\RentangTahunController;
use App\Http\Controllers\Api\VerifikasiAntreanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autentikasi (laravel/fortify, prefix "api")
|--------------------------------------------------------------------------
| POST /api/login, /api/logout, /api/forgot-password, /api/reset-password,
| /api/two-factor-challenge, /api/user/password, /api/user/two-factor-authentication
| didaftarkan otomatis oleh Fortify — lihat config/fortify.php.
*/

// Publik (Guest, read-only). Bukti selalu difilter status "diterima" di level
// query untuk Guest; user login melihat lebih sesuai cakupannya.
Route::name('api.')->group(function () {
    Route::get('/dinas', [DinasController::class, 'index'])->name('dinas.index');
    Route::get('/dinas/{dinas}', [DinasController::class, 'show'])->name('dinas.show');
    Route::get('/dinas/{dinas}/rencana-aksi', [DinasRencanaAksiController::class, 'index'])->name('dinas.rencana-aksi.index');

    Route::get('/rencana-aksi', [RencanaAksiController::class, 'index'])->name('rencana-aksi.index');
    Route::get('/rentang-tahun', RentangTahunController::class)->name('rentang-tahun');
    // Unduh & lihat file bukti ada di routes/web.php (navigasi browser, bukan XHR).
});

// Perlu login (Unit / Admin Pusat)
Route::middleware(['auth:sanctum', 'whitelisted'])->name('api.')->group(function () {
    // Di luar middleware `2fa`: SPA butuh identitas user untuk menampilkan
    // halaman pemasangan 2FA saat Admin Pusat belum mengaktifkannya.
    Route::get('/user', AuthenticatedUserController::class)->name('user');
    Route::get('/rencana-aksi/{rencana_aksi}', [RencanaAksiController::class, 'show'])->name('rencana-aksi.show');
    Route::get('/rencana-aksi/{rencana_aksi}/bukti', [RencanaAksiBuktiController::class, 'index'])->name('rencana-aksi.bukti.index');
    Route::get('/bukti/search', BuktiSearchController::class)->name('bukti.search');
    Route::get('/bukti/{bukti_dukung}', [BuktiDukungController::class, 'show'])->name('bukti.show');
});

// Perlu login + 2FA aktif bagi role yang mewajibkannya (Admin Pusat)
Route::middleware(['auth:sanctum', 'whitelisted', '2fa'])->name('api.')->group(function () {
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::patch('/notifikasi/{id}', [NotifikasiController::class, 'update'])->name('notifikasi.baca');
    Route::delete('/notifikasi/{id}', [NotifikasiController::class, 'destroy'])->name('notifikasi.destroy');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/export', [LaporanController::class, 'export'])->middleware('throttle:ekspor')->name('laporan.export');

    Route::patch('/rencana-aksi/{rencana_aksi}', [RencanaAksiController::class, 'update'])->name('rencana-aksi.update-status');
    Route::post('/rencana-aksi/{rencana_aksi}/bukti', [RencanaAksiBuktiController::class, 'store'])->middleware('throttle:unggah')->name('rencana-aksi.bukti.store');
    Route::delete('/bukti/{bukti_dukung}', [BuktiDukungController::class, 'destroy'])->name('bukti.destroy');

    // Verifikasi bukti (Admin Pusat)
    Route::middleware('role:admin_pusat')->group(function () {
        Route::get('/verifikasi/antrean', VerifikasiAntreanController::class)->name('verifikasi.antrean');
        Route::patch('/bukti/{bukti_dukung}/verifikasi', [BuktiVerifikasiController::class, 'update'])->name('bukti.verifikasi');
    });

    // Khusus Admin Pusat
    Route::middleware('role:admin_pusat')->prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('allowed-users', AllowedUserController::class);
        Route::get('/activity-log', ActivityLogController::class)->name('activity-log');
        Route::post('/rencana-aksi', [AdminRencanaAksiController::class, 'store'])->name('rencana-aksi.store');
        Route::patch('/rencana-aksi/verifikasi-massal', [AdminRencanaAksiController::class, 'ubahVerifikasiMassal'])->name('rencana-aksi.verifikasi-massal');
        Route::patch('/rencana-aksi/{rencana_aksi}', [AdminRencanaAksiController::class, 'update'])->whereUuid('rencana_aksi')->name('rencana-aksi.update');
    });
});
