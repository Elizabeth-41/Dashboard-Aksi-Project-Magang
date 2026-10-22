<?php

namespace App\Http\Controllers\Api;

use App\Exports\LaporanCapaianExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\LaporanRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman Laporan: rekap capaian per dimensi/unit/tahun (JSON) dan export PDF/Excel.
 */
class LaporanController extends Controller
{
    public function index(LaporanRequest $request): JsonResponse
    {
        return response()->json(['data' => $request->laporan()->toArray()]);
    }

    public function export(LaporanRequest $request): Response
    {
        $laporan = $request->laporan();
        $format = $request->validated('format');
        $namaFile = $this->namaFile($laporan->filter(), $format);

        if ($format === 'xlsx') {
            return (new LaporanCapaianExport($laporan))->download($namaFile);
        }

        $data = $laporan->toArray();

        return Pdf::loadView('laporan.capaian', [
            'data' => $data,
            'judulFilter' => LaporanCapaianExport::judulFilter($data['filter']),
        ])
            ->setPaper('a4', 'landscape')
            ->download($namaFile);
    }

    /**
     * @param  array{tahun: ?int, dinas: ?array{id: int, nama: string}, dimensi: ?string}  $filter
     */
    protected function namaFile(array $filter, string $format): string
    {
        $bagian = array_filter([
            'laporan-rencana-aksi',
            $filter['tahun'] !== null ? implode('-', (array) $filter['tahun']) : 'semua-tahun',
            isset($filter['dinas']) ? Str::slug($filter['dinas']['nama']) : null,
            $filter['dimensi'] !== null ? Str::slug($filter['dimensi']) : null,
            now()->format('Ymd'),
        ]);

        return implode('-', $bagian).'.'.$format;
    }
}
