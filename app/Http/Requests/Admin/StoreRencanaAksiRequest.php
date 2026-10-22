<?php

namespace App\Http\Requests\Admin;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Models\RencanaAksi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RencanaAksi::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dinas_id' => ['required', 'integer', Rule::exists('dinas', 'id')],
            'program' => ['nullable', 'string', 'max:2000'],
            'judul' => ['required', 'string', 'max:500'],
            'jenis_keluaran' => ['required', Rule::enum(JenisKeluaran::class)],
            'kategori' => ['required', Rule::enum(Kategori::class)],
            'dimensi' => ['required', Rule::enum(Dimensi::class)],
            'tahun_pelaksanaan' => ['required', 'array', 'min:1'],
            'tahun_pelaksanaan.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
            'tahun_label' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(StatusKegiatan::class)],
            'perlu_verifikasi' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Data siap simpan; tahun diurutkan dan label dibuat otomatis jika kosong.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $tahun = array_values(array_unique(array_map('intval', $this->validated('tahun_pelaksanaan'))));
        sort($tahun);

        $payload = [
            'dinas_id' => (int) $this->validated('dinas_id'),
            'program' => $this->validated('program') ?: null,
            'judul' => trim($this->validated('judul')),
            'jenis_keluaran' => JenisKeluaran::from($this->validated('jenis_keluaran')),
            'kategori' => Kategori::from($this->validated('kategori')),
            'dimensi' => Dimensi::from($this->validated('dimensi')),
            'tahun_pelaksanaan' => $tahun,
            'tahun_label' => $this->validated('tahun_label') ?: self::labelTahun($tahun),
        ];

        if ($this->filled('status')) {
            $payload['status'] = StatusKegiatan::from($this->validated('status'));
        }

        if ($this->has('perlu_verifikasi')) {
            $payload['perlu_verifikasi'] = $this->boolean('perlu_verifikasi');
        }

        return $payload;
    }

    /**
     * "2022 - 2026" untuk rentang kontinu, "2023 & 2025" untuk yang terputus.
     *
     * @param  array<int, int>  $tahun  terurut naik
     */
    public static function labelTahun(array $tahun): string
    {
        if (count($tahun) === 1) {
            return (string) $tahun[0];
        }

        $kontinu = max($tahun) - min($tahun) === count($tahun) - 1;

        return $kontinu
            ? min($tahun).' - '.max($tahun)
            : implode(' & ', $tahun);
    }
}
