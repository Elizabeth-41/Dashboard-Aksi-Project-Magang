<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\RencanaAksi\DeleteRencanaAksi;
use App\Actions\RencanaAksi\LepasBuktiMenunggu;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportRencanaAksiRequest;
use App\Http\Requests\Admin\StoreRencanaAksiRequest;
use App\Http\Requests\Admin\UbahVerifikasiMassalRequest;
use App\Http\Requests\Admin\UpdateRencanaAksiRequest;
use App\Http\Resources\RencanaAksiResource;
use App\Imports\RencanaAksiImport;
use App\Models\QuickWin;
use App\Models\RencanaAksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Tambah/Kelola Kegiatan (khusus Admin Pusat) — kegiatan baru di luar 156 data awal.
 */
class RencanaAksiController extends Controller
{
    public function __construct(private LepasBuktiMenunggu $lepasBukti) {}

    public function store(StoreRencanaAksiRequest $request): JsonResponse
    {
        $rencanaAksi = RencanaAksi::create([
            ...$request->payload(),
            'created_by' => $request->user()->id,
        ]);

        return RencanaAksiResource::make($rencanaAksi->load('dinas'))
            ->response()
            ->setStatusCode(201);
    }

    public function import(ImportRencanaAksiRequest $request): JsonResponse
    {
        $import = new RencanaAksiImport($request->user()->id);
        $import->importFile($request->file('file')->getRealPath());

        return response()->json([
            'message' => "{$import->jumlahDiimpor()} kegiatan berhasil diimpor.",
            'meta' => ['diimpor' => $import->jumlahDiimpor()],
        ], 201);
    }

    public function update(UpdateRencanaAksiRequest $request, RencanaAksi $rencanaAksi): RencanaAksiResource
    {
        $payload = $request->payload();
        $quickWinTahun = $payload['quick_win_tahun'] ?? null;
        unset($payload['quick_win_tahun']);

        DB::transaction(function () use ($rencanaAksi, $payload, $quickWinTahun, $request) {
            $rencanaAksi->update($payload);

            if ($quickWinTahun !== null) {
                $this->syncQuickWins($rencanaAksi->fresh(), $quickWinTahun, $request->user()->id);
            }
        });

        $dilepas = $rencanaAksi->wasChanged('perlu_verifikasi') && ! $rencanaAksi->perlu_verifikasi
            ? $this->lepasBukti->handle($rencanaAksi, $request->user())
            : 0;

        return RencanaAksiResource::make($rencanaAksi->load(['dinas', 'quickWins']))->additional([
            'message' => $dilepas > 0
                ? "Kegiatan diperbarui. {$dilepas} bukti yang masih menunggu langsung diterima."
                : 'Kegiatan diperbarui.',
            'meta' => ['bukti_dilepas' => $dilepas],
        ]);
    }

    public function destroy(RencanaAksi $rencanaAksi, DeleteRencanaAksi $delete): JsonResponse
    {
        Gate::forUser(request()->user())->authorize('delete', $rencanaAksi);
        $delete->handle($rencanaAksi);

        return response()->json([
            'message' => 'Kegiatan dan seluruh bukti pendukungnya berhasil dihapus.',
        ]);
    }

    /**
     * @param  array<int, int>  $tahun
     */
    private function syncQuickWins(RencanaAksi $rencanaAksi, array $tahun, int $adminId): void
    {
        $tahun = array_values(array_unique($tahun));
        $periode = $rencanaAksi->tahun_pelaksanaan;
        $invalid = array_diff($tahun, $periode);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'quick_win_tahun' => 'Quick Win hanya dapat ditetapkan pada tahun pelaksanaan kegiatan.',
            ]);
        }

        foreach ($tahun as $nilaiTahun) {
            $sudahAda = RencanaAksi::query()
                ->where('dimensi', $rencanaAksi->dimensi)
                ->where('id', '!=', $rencanaAksi->id)
                ->whereHas('quickWins', fn ($query) => $query->where('tahun', $nilaiTahun))
                ->exists();

            if ($sudahAda) {
                throw ValidationException::withMessages([
                    'quick_win_tahun' => "Dimensi {$rencanaAksi->dimensi->value} untuk tahun {$nilaiTahun} sudah memiliki Quick Win.",
                ]);
            }

            $jumlah = QuickWin::query()->where('tahun', $nilaiTahun)->where('rencana_aksi_id', '!=', $rencanaAksi->id)->count();
            if ($jumlah >= 6) {
                throw ValidationException::withMessages([
                    'quick_win_tahun' => "Quick Win tahun {$nilaiTahun} sudah mencapai batas maksimal 6 kegiatan.",
                ]);
            }
        }

        $rencanaAksi->quickWins()->delete();
        $rencanaAksi->quickWins()->createMany(array_map(
            fn (int $tahun) => ['tahun' => $tahun, 'created_by' => $adminId],
            $tahun,
        ));
    }

    /**
     * Tetapkan kewajiban verifikasi untuk semua kegiatan yang cocok dengan
     * filter — bukan hanya yang tampil di halaman aktif. Diubah satu per satu
     * (bukan UPDATE massal) agar tiap perubahan tercatat di audit trail.
     */
    public function ubahVerifikasiMassal(UbahVerifikasiMassalRequest $request): JsonResponse
    {
        $perlu = $request->perluVerifikasi();
        $admin = $request->user();

        [$diubah, $dilepas] = DB::transaction(function () use ($request, $perlu, $admin) {
            $kegiatan = RencanaAksi::query()
                ->saring($request->filter())
                ->where('perlu_verifikasi', '!=', $perlu)
                ->get();

            $dilepas = 0;

            foreach ($kegiatan as $item) {
                $item->update(['perlu_verifikasi' => $perlu]);

                if (! $perlu) {
                    $dilepas += $this->lepasBukti->handle($item, $admin);
                }
            }

            return [$kegiatan->count(), $dilepas];
        });

        $pesan = $diubah === 0
            ? 'Tidak ada perubahan: semua kegiatan pada filter ini sudah dalam pengaturan tersebut.'
            : sprintf('%d kegiatan kini %s.', $diubah, $perlu ? 'wajib diverifikasi' : 'tidak perlu verifikasi');

        if ($dilepas > 0) {
            $pesan .= " {$dilepas} bukti yang masih menunggu langsung diterima.";
        }

        return response()->json([
            'message' => $pesan,
            'meta' => ['kegiatan_diubah' => $diubah, 'bukti_dilepas' => $dilepas],
        ]);
    }
}
