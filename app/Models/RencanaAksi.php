<?php

namespace App\Models;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use Database\Factories\RencanaAksiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Kegiatan / rencana aksi Smart City milik satu unit (leading sector).
 */
#[Table('rencana_aksi')]
#[Fillable([
    'dinas_id',
    'program',
    'judul',
    'jenis_keluaran',
    'kategori',
    'dimensi',
    'tahun_label',
    'tahun_pelaksanaan',
    'status',
    'perlu_verifikasi',
    'created_by',
])]
class RencanaAksi extends Model
{
    /** @use HasFactory<RencanaAksiFactory> */
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
     * URL memakai UUID agar ID berurutan tidak terlihat.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Audit trail: perubahan status (manual) dan data kegiatan, dengan nilai
     * sebelum/sesudah, siapa, dan kapan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rencana_aksi')
            ->logOnly(['dinas_id', 'program', 'judul', 'jenis_keluaran', 'kategori', 'dimensi', 'tahun_label', 'tahun_pelaksanaan', 'status', 'perlu_verifikasi'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => $this->deskripsiAktivitas($event));
    }

    protected function deskripsiAktivitas(string $event): string
    {
        return match ($event) {
            'created' => "Menambahkan kegiatan «{$this->judul}»",
            'deleted' => "Menghapus kegiatan «{$this->judul}»",
            'updated' => match (true) {
                $this->wasChanged('perlu_verifikasi') => sprintf(
                    'Menetapkan bukti kegiatan «%s» %s',
                    $this->judul,
                    $this->perlu_verifikasi ? 'wajib diverifikasi admin' : 'langsung diterima tanpa verifikasi',
                ),
                default => $this->wasChanged('status')
                ? sprintf(
                    'Mengubah status kegiatan «%s» dari %s menjadi %s',
                    $this->judul,
                    $this->getOriginal('status')->label(),
                    $this->status->label(),
                )
                    : "Mengubah data kegiatan «{$this->judul}»",
            },
            default => $event,
        };
    }

    /**
     * Mirror default DB agar model baru langsung punya status sebelum disimpan.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'belum_mulai',
        'perlu_verifikasi' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_keluaran' => JenisKeluaran::class,
            'kategori' => Kategori::class,
            'dimensi' => Dimensi::class,
            'tahun_pelaksanaan' => 'array',
            'status' => StatusKegiatan::class,
            'perlu_verifikasi' => 'boolean',
        ];
    }

    public function dinas(): BelongsTo
    {
        return $this->belongsTo(Dinas::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function buktiDukung(): HasMany
    {
        return $this->hasMany(BuktiDukung::class);
    }

    /**
     * Bukti yang boleh dilihat publik (Guest).
     */
    public function buktiDiterima(): HasMany
    {
        return $this->buktiDukung()->diterima();
    }

    /**
     * Terapkan kriteria dari FilterRencanaAksiRequest. Kata kunci mencocokkan
     * judul, program, maupun nama unit pengampu.
     *
     * @param  array{q?: string, dinas?: string, dimensi?: string, jenis_keluaran?: string, kategori?: string, status?: string, tahun?: array<int, int>}  $filter
     */
    #[Scope]
    protected function saring(Builder $query, array $filter): Builder
    {
        return $query
            ->when($filter['q'] ?? null, fn (Builder $q, string $kata) => $q->where(function (Builder $q) use ($kata) {
                $q->where('judul', 'like', "%{$kata}%")
                    ->orWhere('program', 'like', "%{$kata}%")
                    ->orWhereHas('dinas', fn (Builder $dinas) => $dinas->where('nama', 'like', "%{$kata}%"));
            }))
            ->when($filter['dinas'] ?? null, fn (Builder $q, string $slug) => $q->milikUnit($slug))
            ->when($filter['dimensi'] ?? null, fn (Builder $q, string $dimensi) => $q->where('dimensi', $dimensi))
            ->when($filter['jenis_keluaran'] ?? null, fn (Builder $q, string $jenis) => $q->where('jenis_keluaran', $jenis))
            ->when($filter['kategori'] ?? null, fn (Builder $q, string $kategori) => $q->where('kategori', $kategori))
            ->when($filter['status'] ?? null, fn (Builder $q, string $status) => $q->denganStatus(StatusKegiatan::from($status)))
            ->when($filter['tahun'] ?? null, fn (Builder $q, array $tahun) => $q->where(function (Builder $q) use ($tahun) {
                foreach ($tahun as $nilai) {
                    $q->orWhereJsonContains('tahun_pelaksanaan', $nilai);
                }
            }));
    }

    /**
     * Kegiatan milik unit dengan slug tertentu (filter unit di URL memakai slug).
     */
    #[Scope]
    protected function milikUnit(Builder $query, string $slug): Builder
    {
        return $query->whereRelation('dinas', 'slug', $slug);
    }

    /**
     * Kegiatan yang dilaksanakan pada tahun tertentu (periode bisa tidak kontinu).
     */
    #[Scope]
    protected function tahun(Builder $query, int $tahun): Builder
    {
        return $query->whereJsonContains('tahun_pelaksanaan', $tahun);
    }

    #[Scope]
    protected function denganStatus(Builder $query, StatusKegiatan $status): Builder
    {
        return $query->where('status', $status);
    }
}
