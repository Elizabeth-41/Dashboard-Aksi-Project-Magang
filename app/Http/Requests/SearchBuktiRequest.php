<?php

namespace App\Http\Requests;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Enums\StatusVerifikasi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBuktiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'dinas' => ['nullable', 'string', Rule::exists('dinas', 'slug')],
            'dimensi' => ['nullable', Rule::enum(Dimensi::class)],
            'jenis_keluaran' => ['nullable', Rule::enum(JenisKeluaran::class)],
            'kategori' => ['nullable', Rule::enum(Kategori::class)],
            'status' => ['nullable', Rule::enum(StatusKegiatan::class)],
            // Hanya berarti untuk Unit/Admin; Guest selalu dibatasi ke "diterima".
            'status_verifikasi' => ['nullable', Rule::enum(StatusVerifikasi::class)],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tipe_file' => ['nullable', Rule::in(array_keys(config('bukti.mime_groups')))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
