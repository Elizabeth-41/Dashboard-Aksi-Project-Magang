<?php

namespace App\Imports;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Http\Requests\Admin\StoreRencanaAksiRequest;
use App\Models\RencanaAksi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import kegiatan dari template Kelola Kegiatan.
 *
 * Seluruh workbook divalidasi lebih dahulu sehingga import bersifat atomik:
 * jika satu baris tidak valid, tidak ada baris yang disimpan.
 */
class RencanaAksiImport implements ToCollection, WithStartRow
{
    private int $jumlahDiimpor = 0;

    /**
     * @var array<int, array{baris: int, pesan: string}>
     */
    private array $galat = [];

    public function __construct(private int $createdBy) {}

    public function collection(Collection $rows): void
    {
        $payloads = [];

        foreach ($rows as $index => $row) {
            $baris = $index + 2;
            $rawRow = $this->barisTemplate(is_array($row) ? $row : $row->toArray());

            if ($this->barisKosong($rawRow)) {
                continue;
            }

            $nilai = $this->normalisasiBaris($rawRow);
            try {
                $payloads[] = $this->validasiBaris($nilai, $baris);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->galat[] = [
                            'baris' => $baris,
                            'pesan' => "{$field}: {$message}",
                        ];
                    }
                }
            }
        }

        if ($this->galat !== []) {
            throw ValidationException::withMessages([
                'file' => array_map(
                    fn (array $galat): string => "Baris {$galat['baris']}: {$galat['pesan']}",
                    $this->galat,
                ),
            ]);
        }

        $this->tolakDuplikat($payloads);

        DB::transaction(function () use ($payloads): void {
            foreach ($payloads as $payload) {
                RencanaAksi::create([
                    ...$payload,
                    'created_by' => $this->createdBy,
                ]);
            }
        });

        $this->jumlahDiimpor = count($payloads);
    }

    /**
     * Kegiatan dianggap sama jika unit, judul, dan daftar tahun pelaksanaannya
     * sama. Perbedaan status atau metadata lain tidak membuat kegiatan baru.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function tolakDuplikat(array $payloads): void
    {
        $kunciDalamFile = [];
        $kandidat = RencanaAksi::query()
            ->whereIn('dinas_id', array_unique(array_map(
                fn (array $payload): int => $payload['dinas_id'],
                $payloads,
            )))
            ->whereIn('judul', array_unique(array_map(
                fn (array $payload): string => $payload['judul'],
                $payloads,
            )))
            ->get(['dinas_id', 'judul', 'tahun_pelaksanaan']);

        foreach ($payloads as $index => $payload) {
            $kunci = $this->kunciDuplikat($payload);

            if (isset($kunciDalamFile[$kunci])) {
                $this->galat[] = [
                    'baris' => $index + 2,
                    'pesan' => sprintf(
                        'Kegiatan "%s" untuk unit ID %d pada tahun %s duplikat dengan baris %d dalam file yang sama. Hapus atau perbaiki baris ini, lalu impor ulang.',
                        $payload['judul'],
                        $payload['dinas_id'],
                        implode(', ', $payload['tahun_pelaksanaan']),
                        $kunciDalamFile[$kunci] + 2,
                    ),
                ];

                continue;
            }

            $kunciDalamFile[$kunci] = $index;
            $sudahAda = $kandidat->contains(
                fn (RencanaAksi $rencana): bool => $this->kunciDuplikat([
                    'dinas_id' => $rencana->dinas_id,
                    'judul' => $rencana->judul,
                    'tahun_pelaksanaan' => $rencana->tahun_pelaksanaan,
                ]) === $kunci,
            );

            if ($sudahAda) {
                $this->galat[] = [
                    'baris' => $index + 2,
                    'pesan' => sprintf(
                        'Kegiatan "%s" untuk unit ID %d pada tahun %s sudah ada. Hapus atau perbaiki baris ini di file Excel, lalu impor ulang.',
                        $payload['judul'],
                        $payload['dinas_id'],
                        implode(', ', $payload['tahun_pelaksanaan']),
                    ),
                ];
            }
        }

        if ($this->galat !== []) {
            throw ValidationException::withMessages([
                'file' => array_map(
                    fn (array $galat): string => "Baris {$galat['baris']}: {$galat['pesan']}",
                    $this->galat,
                ),
            ]);
        }
    }

    /**
     * @param  array{dinas_id: int, judul: string, tahun_pelaksanaan: array<int, int>}  $payload
     */
    private function kunciDuplikat(array $payload): string
    {
        return implode('|', [
            $payload['dinas_id'],
            mb_strtolower(trim($payload['judul'])),
            implode(',', $payload['tahun_pelaksanaan']),
        ]);
    }

    /**
     * Baca sheet aktif langsung berdasarkan koordinat A:I. Ini menghindari
     * perubahan key/header yang dapat dilakukan reader Excel.
     */
    public function importFile(string $path): void
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = new Collection;

        for ($baris = 2; $baris <= $sheet->getHighestRow(); $baris++) {
            $rows->push([
                $sheet->getCell("A{$baris}")->getValue(),
                $sheet->getCell("B{$baris}")->getValue(),
                $sheet->getCell("C{$baris}")->getValue(),
                $sheet->getCell("D{$baris}")->getValue(),
                $sheet->getCell("E{$baris}")->getValue(),
                $sheet->getCell("F{$baris}")->getValue(),
                $sheet->getCell("G{$baris}")->getValue(),
                $sheet->getCell("H{$baris}")->getValue(),
                $sheet->getCell("I{$baris}")->getValue(),
            ]);
        }

        $this->collection($rows);
    }

    public function startRow(): int
    {
        return 2;
    }

    public function jumlahDiimpor(): int
    {
        return $this->jumlahDiimpor;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalisasiBaris(array $row): array
    {
        $tahun = $row['tahun_pelaksanaan'] ?? null;

        if (is_string($tahun)) {
            $tahun = trim($tahun);
            $tahun = str_starts_with($tahun, '[')
                ? json_decode($tahun, true)
                : preg_split('/\s*[,;]\s*/', $tahun, -1, PREG_SPLIT_NO_EMPTY);
        } elseif (is_numeric($tahun)) {
            $tahun = [(int) $tahun];
        }

        return [
            'dinas_id' => $this->angka($row['dinas_id'] ?? null),
            'judul' => is_string($row['judul'] ?? null) ? trim($row['judul']) : $row['judul'] ?? null,
            'program' => is_string($row['program'] ?? null) ? trim($row['program']) : $row['program'] ?? null,
            'dimensi' => $this->teks($row['dimensi'] ?? null),
            'jenis_keluaran' => $this->teks($row['jenis_keluaran'] ?? null),
            'kategori' => $this->teks($row['kategori'] ?? null),
            'status' => $this->teks($row['status'] ?? null) ?: StatusKegiatan::BelumMulai->value,
            'perlu_verifikasi' => $this->boolean($row['perlu_verifikasi'] ?? null, true),
            'tahun_pelaksanaan' => is_array($tahun) ? array_map(fn (mixed $year): mixed => $this->angka($year), $tahun) : $tahun,
        ];
    }

    /**
     * Excel dapat menyimpan spasi, baris baru, atau variasi kapitalisasi pada
     * header. Samakan kunci sebelum field dibaca agar template tetap toleran.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<string|int, mixed>
     */
    private function normalisasiKunci(array $row): array
    {
        $hasil = [];
        $namaKolom = [
            'dinasid' => 'dinas_id',
            'judul' => 'judul',
            'program' => 'program',
            'dimensi' => 'dimensi',
            'jeniskeluaran' => 'jenis_keluaran',
            'kategori' => 'kategori',
            'status' => 'status',
            'perluverifikasi' => 'perlu_verifikasi',
            'perludiverifikasi' => 'perlu_verifikasi',
            'tahunpelaksanaan' => 'tahun_pelaksanaan',
        ];

        foreach ($row as $key => $value) {
            if (is_int($key)) {
                $hasil[$key] = $value;

                continue;
            }

            $kunci = strtolower(trim(preg_replace('/\s+/', '_', str_replace("\xC2\xA0", ' ', $key)) ?? $key));
            $kunci = trim($kunci, "\xEF\xBB\xBF \t\n\r\0\x0B");
            $kanonik = preg_replace('/[^a-z0-9]/', '', $kunci) ?? $kunci;
            $hasil[$namaKolom[$kanonik] ?? $kunci] = $value;
        }

        return $hasil;
    }

    /**
     * Template import memiliki sembilan kolom tetap. Pemetaan posisi ini
     * menghindari ketergantungan pada header atau metadata tabel Excel.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<string, mixed>
     */
    private function barisTemplate(array $row): array
    {
        if (array_filter(array_keys($row), 'is_string') !== []) {
            return $this->lengkapiKolomTemplate($this->normalisasiKunci($row));
        }

        $nilai = array_values($row);
        $namaKolom = [
            'dinas_id',
            'judul',
            'program',
            'dimensi',
            'jenis_keluaran',
            'kategori',
            'status',
            'perlu_verifikasi',
            'tahun_pelaksanaan',
        ];

        return array_combine(
            $namaKolom,
            array_pad(array_slice($nilai, 0, count($namaKolom)), count($namaKolom), null),
        );
    }

    /**
     * Fallback untuk workbook yang mengubah atau menghilangkan nama header:
     * template memiliki urutan kolom yang tetap.
     *
     * @param  array<string|int, mixed>  $row
     * @return array<string|int, mixed>
     */
    private function lengkapiKolomTemplate(array $row): array
    {
        $kolom = [
            'dinas_id',
            'judul',
            'program',
            'dimensi',
            'jenis_keluaran',
            'kategori',
            'status',
            'perlu_verifikasi',
            'tahun_pelaksanaan',
        ];
        $posisi = array_values($row);

        foreach ($kolom as $index => $nama) {
            if (! array_key_exists($nama, $row) || ($row[$nama] === null && isset($posisi[$index]))) {
                $row[$nama] = $posisi[$index] ?? null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validasiBaris(array $row, int $baris): array
    {
        $validator = Validator::make($row, [
            'dinas_id' => ['required', 'integer', Rule::exists('dinas', 'id')],
            'judul' => ['required', 'string', 'max:500'],
            'program' => ['nullable', 'string', 'max:2000'],
            'dimensi' => ['required', Rule::enum(Dimensi::class)],
            'jenis_keluaran' => ['required', Rule::enum(JenisKeluaran::class)],
            'kategori' => ['required', Rule::enum(Kategori::class)],
            'status' => ['required', Rule::enum(StatusKegiatan::class)],
            'perlu_verifikasi' => ['required', 'boolean'],
            'tahun_pelaksanaan' => ['required', 'array', 'min:1'],
            'tahun_pelaksanaan.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
        ], [
            'dinas_id.exists' => 'ID OPD/Unit tidak ditemukan.',
            'tahun_pelaksanaan.array' => 'Isi tahun dengan format 2025,2026.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $tahun = array_values(array_unique(array_map('intval', $validated['tahun_pelaksanaan'])));
        sort($tahun);

        return [
            'dinas_id' => (int) $validated['dinas_id'],
            'program' => $validated['program'] ?: null,
            'judul' => trim($validated['judul']),
            'jenis_keluaran' => JenisKeluaran::from($validated['jenis_keluaran']),
            'kategori' => Kategori::from($validated['kategori']),
            'dimensi' => Dimensi::from($validated['dimensi']),
            'tahun_pelaksanaan' => $tahun,
            'tahun_label' => StoreRencanaAksiRequest::labelTahun($tahun),
            'status' => StatusKegiatan::from($validated['status']),
            'perlu_verifikasi' => (bool) $validated['perlu_verifikasi'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function barisKosong(array $row): bool
    {
        return count(array_filter($row, fn (mixed $value): bool => $value !== null && trim((string) $value) !== '')) === 0;
    }

    private function teks(mixed $value): ?string
    {
        return $value === null ? null : trim((string) $value);
    }

    private function angka(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    private function boolean(mixed $value, bool $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? (is_numeric($value) ? ((int) $value === 1) : $value);
    }
}
