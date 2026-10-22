<?php

namespace App\Reports;

use App\Enums\Dimensi;
use App\Enums\StatusKegiatan;
use App\Enums\StatusVerifikasi;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Support\RentangTahun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Rekap capaian Rencana Aksi per dimensi / unit / tahun, dihitung di PHP dari
 * satu kali query (data ratusan baris) supaya JSON, Excel, dan PDF memakai
 * angka yang persis sama.
 */
class LaporanCapaian
{
    /** @var Collection<int, RencanaAksi>|null */
    protected ?Collection $kegiatan = null;

    public function __construct(
        public readonly array $tahun = [],
        public readonly ?int $dinasId = null,
        public readonly ?Dimensi $dimensi = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter(),
            'ringkasan' => $this->ringkasan(),
            'per_dimensi' => $this->perDimensi()->values()->all(),
            'per_unit' => $this->perUnit()->values()->all(),
            'per_tahun' => $this->perTahun()->values()->all(),
            'kegiatan' => $this->daftarKegiatan()->all(),
        ];
    }

    /**
     * @return array{tahun: array<int, int>, dinas: ?array{id: int, nama: string}, dimensi: ?string, dibuat_pada: string}
     */
    public function filter(): array
    {
        $dinas = $this->dinasId !== null ? Dinas::query()->find($this->dinasId) : null;

        return [
            'tahun' => count($this->tahun) === 1 ? $this->tahun[0] : ($this->tahun ?: null),
            'dinas' => $dinas ? ['id' => $dinas->id, 'nama' => $dinas->nama] : null,
            'dimensi' => $this->dimensi?->value,
            'dibuat_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function ringkasan(): array
    {
        $kegiatan = $this->kegiatan();

        return [
            ...$this->hitungStatus($kegiatan),
            'jumlah_unit' => $kegiatan->pluck('dinas_id')->unique()->count(),
            'bukti_diterima' => (int) $kegiatan->sum('bukti_diterima_count'),
            'bukti_menunggu' => (int) $kegiatan->sum('bukti_menunggu_count'),
            'bukti_ditolak' => (int) $kegiatan->sum('bukti_ditolak_count'),
        ];
    }

    /**
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function perDimensi(): SupportCollection
    {
        $kegiatan = $this->kegiatan();

        return collect(Dimensi::cases())
            ->when($this->dimensi !== null, fn ($cases) => $cases->filter(fn (Dimensi $d) => $d === $this->dimensi))
            ->map(fn (Dimensi $dimensi) => [
                'dimensi' => $dimensi->value,
                ...$this->hitungStatus($kegiatan->where('dimensi', $dimensi)),
                'bukti_diterima' => (int) $kegiatan->where('dimensi', $dimensi)->sum('bukti_diterima_count'),
            ]);
    }

    /**
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function perUnit(): SupportCollection
    {
        return $this->kegiatan()
            ->groupBy('dinas_id')
            ->map(fn (Collection $kegiatan) => [
                'dinas_id' => $kegiatan->first()->dinas_id,
                'dinas' => $kegiatan->first()->dinas->nama,
                ...$this->hitungStatus($kegiatan),
                'bukti_diterima' => (int) $kegiatan->sum('bukti_diterima_count'),
            ])
            ->sortBy('dinas', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Rekap per tahun sepanjang rentang data; satu kegiatan multi-tahun dihitung
     * di tiap tahunnya.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function perTahun(): SupportCollection
    {
        $kegiatan = $this->kegiatan();
        if ($this->tahun !== []) {
            $rentang = $this->tahun;
        } else {
            ['awal' => $awal, 'akhir' => $akhir] = app(RentangTahun::class)->hitung();
            $rentang = range($awal, $akhir);
        }

        return collect($rentang)->map(fn (int $tahun) => [
            'tahun' => $tahun,
            ...$this->hitungStatus($kegiatan->filter(fn (RencanaAksi $r) => in_array($tahun, $r->tahun_pelaksanaan, true))),
        ]);
    }

    /**
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function daftarKegiatan(): SupportCollection
    {
        return $this->kegiatan()
            ->sortBy([['dimensi', 'asc'], ['dinas.nama', 'asc'], ['judul', 'asc']])
            ->values()
            ->map(fn (RencanaAksi $r) => [
                'id' => $r->id,
                'dimensi' => $r->dimensi->value,
                'unit' => $r->dinas->nama,
                'program' => $r->program,
                'judul' => $r->judul,
                'jenis_keluaran' => $r->jenis_keluaran->label(),
                'kategori' => $r->kategori->label(),
                'tahun' => $r->tahun_label,
                'status' => $r->status->label(),
                'bukti_diterima' => (int) $r->bukti_diterima_count,
                'bukti_menunggu' => (int) $r->bukti_menunggu_count,
                'bukti_ditolak' => (int) $r->bukti_ditolak_count,
            ]);
    }

    /**
     * @return Collection<int, RencanaAksi>
     */
    protected function kegiatan(): Collection
    {
        return $this->kegiatan ??= RencanaAksi::query()
            ->with('dinas')
            ->withCount([
                'buktiDukung as bukti_diterima_count' => fn (Builder $q) => $q->where('status_verifikasi', StatusVerifikasi::Diterima),
                'buktiDukung as bukti_menunggu_count' => fn (Builder $q) => $q->where('status_verifikasi', StatusVerifikasi::Menunggu),
                'buktiDukung as bukti_ditolak_count' => fn (Builder $q) => $q->where('status_verifikasi', StatusVerifikasi::Ditolak),
            ])
            ->when($this->tahun !== [], function (Builder $q) {
                $q->where(function (Builder $q) {
                    foreach ($this->tahun as $tahun) {
                        $q->orWhereJsonContains('tahun_pelaksanaan', $tahun);
                    }
                });
            })
            ->when($this->dinasId !== null, fn (Builder $q) => $q->where('dinas_id', $this->dinasId))
            ->when($this->dimensi !== null, fn (Builder $q) => $q->where('dimensi', $this->dimensi))
            ->get();
    }

    /**
     * @param  SupportCollection<int, RencanaAksi>  $kegiatan
     * @return array{total: int, belum_mulai: int, berjalan: int, selesai: int, persentase: int}
     */
    protected function hitungStatus(SupportCollection $kegiatan): array
    {
        $total = $kegiatan->count();
        $selesai = $kegiatan->where('status', StatusKegiatan::Selesai)->count();

        return [
            'total' => $total,
            'belum_mulai' => $kegiatan->where('status', StatusKegiatan::BelumMulai)->count(),
            'berjalan' => $kegiatan->where('status', StatusKegiatan::Berjalan)->count(),
            'selesai' => $selesai,
            'persentase' => $total === 0 ? 0 : (int) round($selesai / $total * 100),
        ];
    }
}
