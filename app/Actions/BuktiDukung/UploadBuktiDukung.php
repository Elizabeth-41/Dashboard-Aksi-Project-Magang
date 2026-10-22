<?php

namespace App\Actions\BuktiDukung;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Notifications\BuktiBaruMenungguVerifikasi;
use App\Support\PenerimaNotifikasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simpan file ke disk privat dengan nama acak, catat MIME hasil deteksi isi
 * file, buat entri bukti berstatus "menunggu", lalu beri tahu admin dalam cakupan.
 */
class UploadBuktiDukung
{
    public function __construct(private PenerimaNotifikasi $penerima) {}

    public function handle(RencanaAksi $rencanaAksi, UploadedFile $file, string $deskripsi, User $uploader): BuktiDukung
    {
        $disk = config('bukti.disk');
        $directory = config('bukti.path').'/'.$rencanaAksi->id;
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs($directory, $filename, $disk);

        // Kegiatan yang ditandai tidak perlu verifikasi: bukti langsung diterima
        // dan tidak masuk antrean admin.
        $perluVerifikasi = $rencanaAksi->perlu_verifikasi;

        try {
            $bukti = $rencanaAksi->buktiDukung()->create([
                'nama_file' => $file->getClientOriginalName(),
                'path_file' => $path,
                'tipe_file' => $file->getMimeType(),
                'ukuran_file' => $file->getSize(),
                'deskripsi' => $deskripsi,
                'uploaded_by' => $uploader->id,
                'status_verifikasi' => $perluVerifikasi ? StatusVerifikasi::Menunggu : StatusVerifikasi::Diterima,
                'verified_at' => $perluVerifikasi ? null : now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        $bukti->setRelation('rencanaAksi', $rencanaAksi->loadMissing('dinas'));

        if ($perluVerifikasi) {
            // Pengunggah sendiri (mis. admin yang mengunggah atas nama unit) tidak perlu diberi tahu.
            $admin = $this->penerima->adminPusat()->reject(fn (User $user) => $user->is($uploader));

            Notification::send($admin, new BuktiBaruMenungguVerifikasi($bukti));
        }

        return $bukti;
    }
}
