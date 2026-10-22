<?php

namespace App\Http\Requests;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kriteria penyaringan daftar kegiatan. Dipakai bersama oleh daftar publik dan
 * aksi massal admin, supaya "kegiatan yang tampil" dan "kegiatan yang diubah
 * massal" selalu himpunan yang sama.
 */
class FilterRencanaAksiRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('tahun') && ! is_array($this->input('tahun'))) {
            $this->merge(['tahun' => [$this->input('tahun')]]);
        }
    }

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
            'tahun' => ['nullable', 'array'],
            'tahun.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Hanya kriteria penyaring (tanpa per_page/page).
     *
     * @return array{q?: string, dinas?: string, dimensi?: string, jenis_keluaran?: string, kategori?: string, status?: string, tahun?: array<int, int>}
     */
    public function filter(): array
    {
        $filter = array_filter(
            $this->safe()->only(['q', 'dinas', 'dimensi', 'jenis_keluaran', 'kategori', 'status', 'tahun']),
            fn ($nilai) => $nilai !== null && $nilai !== '',
        );

        if (isset($filter['tahun'])) {
            $filter['tahun'] = array_values(array_map('intval', $filter['tahun']));
        }

        return $filter;
    }
}
