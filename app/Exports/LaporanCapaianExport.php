<?php

namespace App\Exports;

use App\Reports\LaporanCapaian;
use App\Support\RentangTahun;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Workbook Excel: Ringkasan, Per Dimensi, Per Unit, Per Tahun, Daftar Kegiatan.
 */
class LaporanCapaianExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(private LaporanCapaian $laporan) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $data = $this->laporan->toArray();
        $judulFilter = self::judulFilter($data['filter']);

        return [
            new LaporanSheet('Ringkasan', ['Indikator', 'Nilai'], [
                ['Laporan', 'Rekap Capaian Rencana Aksi Smart City Banjarmasin'],
                ['Filter', $judulFilter],
                ['Dibuat pada', now()->translatedFormat('d F Y H:i')],
                ['Jumlah unit', $data['ringkasan']['jumlah_unit']],
                ['Total kegiatan', $data['ringkasan']['total']],
                ['Belum Mulai', $data['ringkasan']['belum_mulai']],
                ['Berjalan', $data['ringkasan']['berjalan']],
                ['Selesai', $data['ringkasan']['selesai']],
                ['Persentase selesai (%)', $data['ringkasan']['persentase']],
                ['Bukti diterima', $data['ringkasan']['bukti_diterima']],
                ['Bukti menunggu verifikasi', $data['ringkasan']['bukti_menunggu']],
                ['Bukti ditolak', $data['ringkasan']['bukti_ditolak']],
            ]),
            new LaporanSheet(
                'Per Dimensi',
                ['Dimensi', 'Total', 'Belum Mulai', 'Berjalan', 'Selesai', 'Selesai (%)', 'Bukti Diterima'],
                array_map(fn (array $r) => [$r['dimensi'], $r['total'], $r['belum_mulai'], $r['berjalan'], $r['selesai'], $r['persentase'], $r['bukti_diterima']], $data['per_dimensi']),
            ),
            new LaporanSheet(
                'Per Unit',
                ['Unit / OPD', 'Total', 'Belum Mulai', 'Berjalan', 'Selesai', 'Selesai (%)', 'Bukti Diterima'],
                array_map(fn (array $r) => [$r['dinas'], $r['total'], $r['belum_mulai'], $r['berjalan'], $r['selesai'], $r['persentase'], $r['bukti_diterima']], $data['per_unit']),
            ),
            new LaporanSheet(
                'Per Tahun',
                ['Tahun', 'Total', 'Belum Mulai', 'Berjalan', 'Selesai', 'Selesai (%)'],
                array_map(fn (array $r) => [$r['tahun'], $r['total'], $r['belum_mulai'], $r['berjalan'], $r['selesai'], $r['persentase']], $data['per_tahun']),
            ),
            new LaporanSheet(
                'Daftar Kegiatan',
                ['No', 'Dimensi', 'Unit / OPD', 'Program', 'Kegiatan / Rencana Aksi', 'Jenis Keluaran', 'Kategori', 'Tahun', 'Status', 'Bukti Diterima', 'Bukti Menunggu', 'Bukti Ditolak'],
                array_map(fn (array $r, int $i) => [$i + 1, $r['dimensi'], $r['unit'], $r['program'], $r['judul'], $r['jenis_keluaran'], $r['kategori'], $r['tahun'], $r['status'], $r['bukti_diterima'], $r['bukti_menunggu'], $r['bukti_ditolak']], $data['kegiatan'], array_keys($data['kegiatan'])),
            ),
        ];
    }

    public static function labelSemuaTahun(): string
    {
        ['awal' => $awal, 'akhir' => $akhir] = app(RentangTahun::class)->hitung();

        return "Semua tahun ({$awal}-{$akhir})";
    }

    /**
     * @param  array{tahun: array<int, int>, dinas: ?array{id: int, nama: string}, dimensi: ?string}  $filter
     */
    public static function judulFilter(array $filter): string
    {
        $bagian = array_filter([
            $filter['tahun'] !== null ? 'Tahun '.implode(', ', (array) $filter['tahun']) : self::labelSemuaTahun(),
            $filter['dinas']['nama'] ?? null,
            $filter['dimensi'],
        ]);

        return implode(' · ', $bagian);
    }
}
