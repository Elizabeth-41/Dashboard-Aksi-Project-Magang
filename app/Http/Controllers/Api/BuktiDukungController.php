<?php

namespace App\Http\Controllers\Api;

use App\Actions\BuktiDukung\DeleteBuktiDukung;
use App\Http\Controllers\Controller;
use App\Http\Resources\BuktiDukungResource;
use App\Models\BuktiDukung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuktiDukungController extends Controller
{
    public function show(Request $request, BuktiDukung $buktiDukung): BuktiDukungResource
    {
        Gate::forUser($request->user('sanctum'))->authorize('view', $buktiDukung);

        return BuktiDukungResource::make($buktiDukung->load(['rencanaAksi.dinas', 'uploader', 'verifier']));
    }

    /**
     * Unduh file. Publik untuk bukti "diterima", selebihnya sesuai cakupan.
     */
    public function download(Request $request, BuktiDukung $buktiDukung): StreamedResponse
    {
        Gate::forUser($request->user('sanctum'))->authorize('view', $buktiDukung);

        $disk = Storage::disk(config('bukti.disk'));

        abort_unless($disk->exists($buktiDukung->path_file), 404, 'File bukti tidak ditemukan di penyimpanan.');

        return $disk->download($buktiDukung->path_file, $buktiDukung->nama_file, [
            'Content-Type' => $buktiDukung->tipe_file,
        ]);
    }

    /**
     * Tampilkan file langsung di tab baru (tanpa mengunduh). Hak akses sama
     * dengan unduh. Jenis yang tidak bisa dirender browser tetap diunduh.
     */
    public function lihat(Request $request, BuktiDukung $buktiDukung, ?string $nama = null): StreamedResponse
    {
        Gate::forUser($request->user('sanctum'))->authorize('view', $buktiDukung);

        $disk = Storage::disk(config('bukti.disk'));

        abort_unless($disk->exists($buktiDukung->path_file), 404, 'File bukti tidak ditemukan di penyimpanan.');

        $header = [
            'Content-Type' => $buktiDukung->tipe_file,
            'Cache-Control' => 'private, no-cache',
        ];

        if (! $buktiDukung->bisaDilihatLangsung()) {
            return $disk->download($buktiDukung->path_file, $buktiDukung->nama_file, $header);
        }

        // Gambar ditampilkan dalam dokumen terisolasi tanpa skrip. PDF tidak
        // diberi "sandbox" karena penampil PDF Chromium menolak tampil di
        // dokumen sandbox; PDF sudah terisolasi di penampil bawaan browser.
        if ($buktiDukung->kelompokTipeFile() === 'gambar') {
            $header['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        $disposisi = $buktiDukung->kelompokTipeFile() === 'gambar' ? 'inline' : 'attachment';

        return $disk->response($buktiDukung->path_file, $buktiDukung->nama_file, $header, $disposisi);
    }

    public function destroy(Request $request, BuktiDukung $buktiDukung, DeleteBuktiDukung $delete): JsonResponse
    {
        Gate::forUser($request->user('sanctum'))->authorize('delete', $buktiDukung);

        $delete->handle($buktiDukung);

        return response()->json(status: 204);
    }
}
