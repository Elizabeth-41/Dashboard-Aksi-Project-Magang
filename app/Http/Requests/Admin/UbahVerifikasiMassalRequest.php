<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\FilterRencanaAksiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tetapkan kewajiban verifikasi untuk seluruh kegiatan yang cocok dengan
 * filter yang sama dengan daftar di Kelola Kegiatan.
 */
class UbahVerifikasiMassalRequest extends FilterRencanaAksiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminPusat() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'perlu_verifikasi' => ['required', 'boolean'],
        ];
    }

    public function perluVerifikasi(): bool
    {
        return $this->boolean('perlu_verifikasi');
    }
}
