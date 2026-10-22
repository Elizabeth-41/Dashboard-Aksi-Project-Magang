<?php

use App\Http\Controllers\Api\BuktiDukungController;
use Illuminate\Support\Facades\Route;

/*
 * Unduh & lihat file bukti adalah navigasi browser (tab baru / berkas), bukan
 * XHR dari SPA. Dilayani di grup web agar sesi login terbaca langsung dari
 * cookie — di grup API, Sanctum hanya mengenali sesi bila ada header Referer,
 * sehingga tautan yang ditempel langsung atau Referer yang dibuang browser
 * membuat bukti non-publik ditolak walau pengguna sedang login. URL & nama rute
 * tetap /api/... supaya tidak ada yang berubah bagi pemakainya.
 */
Route::middleware('throttle:api')->prefix('api')->name('api.')->group(function () {
    Route::get('/bukti/{bukti_dukung}/download', [BuktiDukungController::class, 'download'])->name('bukti.download');
    // {nama} hanya kosmetik: browser memakai ujung URL sebagai judul tab, jadi
    // tab tiap bukti berjudul nama filenya, bukan "lihat". Isinya diabaikan.
    Route::get('/bukti/{bukti_dukung}/lihat/{nama?}', [BuktiDukungController::class, 'lihat'])
        ->where('nama', '[^/]*')
        ->name('bukti.lihat');
});

/*
 * SPA React menangani seluruh routing halaman; Laravel hanya menyajikan
 * shell-nya. Rute API, Fortify, Sanctum, dan health check tidak ikut tertangkap
 * karena didaftarkan lebih dulu / memakai prefix sendiri.
 */
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|storage|up|build).*$')
    ->name('spa');
