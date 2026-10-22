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

class UpdateRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RencanaAksi $rencanaAksi */
        $rencanaAksi = $this->route('rencana_aksi');

        return $this->user()?->can('update', $rencanaAksi) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dinas_id' => ['sometimes', 'required', 'integer', Rule::exists('dinas', 'id')],
            'program' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'judul' => ['sometimes', 'required', 'string', 'max:500'],
            'jenis_keluaran' => ['sometimes', 'required', Rule::enum(JenisKeluaran::class)],
            'kategori' => ['sometimes', 'required', Rule::enum(Kategori::class)],
            'dimensi' => ['sometimes', 'required', Rule::enum(Dimensi::class)],
            'tahun_pelaksanaan' => ['sometimes', 'required', 'array', 'min:1'],
            'tahun_pelaksanaan.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
            'tahun_label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'required', Rule::enum(StatusKegiatan::class)],
            'perlu_verifikasi' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Hanya field yang dikirim; tahun_label dibuat ulang jika tahun berubah tanpa label.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = $this->safe()->except(['tahun_pelaksanaan', 'tahun_label']);

        if ($this->has('tahun_pelaksanaan')) {
            $tahun = array_values(array_unique(array_map('intval', $this->validated('tahun_pelaksanaan'))));
            sort($tahun);

            $payload['tahun_pelaksanaan'] = $tahun;
            $payload['tahun_label'] = $this->validated('tahun_label') ?: StoreRencanaAksiRequest::labelTahun($tahun);
        } elseif ($this->filled('tahun_label')) {
            $payload['tahun_label'] = $this->validated('tahun_label');
        }

        if (array_key_exists('program', $payload)) {
            $payload['program'] = $payload['program'] ?: null;
        }

        if ($this->has('perlu_verifikasi')) {
            $payload['perlu_verifikasi'] = $this->boolean('perlu_verifikasi');
        }

        return $payload;
    }
}
