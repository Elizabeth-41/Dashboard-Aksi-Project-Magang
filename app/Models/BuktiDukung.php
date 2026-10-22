<?php

namespace App\Models;

use App\Enums\StatusVerifikasi;
use Database\Factories\BuktiDukungFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * File bukti dukung sebuah kegiatan, dengan status verifikasi admin.
 */
#[Table('bukti_dukung')]
#[Fillable([
    'rencana_aksi_id',
    'nama_file',
    'path_file',
    'tipe_file',
    'ukuran_file',
    'deskripsi',
    'uploaded_by',
    'status_verifikasi',
    'alasan_penolakan',
    'verified_by',
    'verified_at',
])]
class BuktiDukung extends Model
{
    /** @use HasFactory<BuktiDukungFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * UUID hanya untuk kolom `uuid`; kunci utama tetap auto increment.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * URL (detail, lihat, unduh, verifikasi) memakai UUID agar ID berurutan tidak terlihat.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Audit trail: unggah, verifikasi/penolakan, dan penghapusan bukti.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('bukti_dukung')
            ->logOnly(['rencana_aksi_id', 'nama_file', 'deskripsi', 'status_verifikasi', 'alasan_penolakan', 'verified_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => $this->deskripsiAktivitas($event));
    }

    protected function deskripsiAktivitas(string $event): string
    {
        $judul = $this->rencanaAksi?->judul ?? "#{$this->rencana_aksi_id}";

        return match ($event) {
            'created' => "Mengunggah bukti «{$this->nama_file}» untuk kegiatan «{$judul}»",
            'deleted' => "Menghapus bukti «{$this->nama_file}» dari kegiatan «{$judul}»",
            'updated' => match (true) {
                ! $this->wasChanged('status_verifikasi') => "Mengubah bukti «{$this->nama_file}»",
                $this->status_verifikasi === StatusVerifikasi::Diterima => "Menerima bukti «{$this->nama_file}» untuk kegiatan «{$judul}»",
                $this->status_verifikasi === StatusVerifikasi::Ditolak => "Menolak bukti «{$this->nama_file}» untuk kegiatan «{$judul}»: {$this->alasan_penolakan}",
                default => "Mengembalikan bukti «{$this->nama_file}» ke status menunggu",
            },
            default => $event,
        };
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_verifikasi' => 'menunggu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ukuran_file' => 'integer',
            'status_verifikasi' => StatusVerifikasi::class,
            'verified_at' => 'datetime',
        ];
    }

    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Hanya bukti "diterima" — wajib dipakai untuk semua query yang tampil ke Guest.
     */
    #[Scope]
    protected function diterima(Builder $query): Builder
    {
        return $query->where('status_verifikasi', StatusVerifikasi::Diterima);
    }

    /**
     * Antrean verifikasi.
     */
    #[Scope]
    protected function menunggu(Builder $query): Builder
    {
        return $query->where('status_verifikasi', StatusVerifikasi::Menunggu);
    }

    /**
     * Bukti yang boleh dilihat user tertentu: "diterima" untuk semua orang
     * (termasuk Guest), status lain hanya untuk unit pemilik & admin dalam cakupan.
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdminPusat()) {
            return $query;
        }

        $cakupan = $user?->cakupanDinasIds() ?? [];

        return $query->where(function (Builder $query) use ($cakupan) {
            $query->where('status_verifikasi', StatusVerifikasi::Diterima);

            if ($cakupan !== []) {
                $query->orWhereHas('rencanaAksi', fn (Builder $rencana) => $rencana->whereIn('dinas_id', $cakupan));
            }
        });
    }

    /**
     * Filter berdasarkan kelompok tipe file (pdf/gambar/dokumen) dari config bukti.
     */
    #[Scope]
    protected function kelompokTipe(Builder $query, string $kelompok): Builder
    {
        return $query->whereIn('tipe_file', config("bukti.mime_groups.{$kelompok}", []));
    }

    public function isDiterima(): bool
    {
        return $this->status_verifikasi === StatusVerifikasi::Diterima;
    }

    /**
     * Hanya PDF dan gambar yang boleh ditampilkan langsung di browser; dokumen
     * Office tidak bisa dirender browser sehingga tetap diunduh. Jenisnya dari
     * MIME hasil deteksi isi file saat unggah, bukan dari nama file.
     */
    public function bisaDilihatLangsung(): bool
    {
        return in_array($this->kelompokTipeFile(), ['pdf', 'gambar'], true);
    }

    /**
     * Nama kelompok tipe file (pdf/gambar/dokumen) untuk tampilan & filter.
     */
    public function kelompokTipeFile(): ?string
    {
        foreach (config('bukti.mime_groups', []) as $kelompok => $mimes) {
            if (in_array($this->tipe_file, $mimes, true)) {
                return $kelompok;
            }
        }

        return null;
    }
}
