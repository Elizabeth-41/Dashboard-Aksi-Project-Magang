<?php

namespace App\Http\Requests;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyBuktiDukungRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BuktiDukung $buktiDukung */
        $buktiDukung = $this->route('bukti_dukung');

        return $this->user()?->can('verify', $buktiDukung) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status_verifikasi' => ['required', Rule::in([StatusVerifikasi::Diterima->value, StatusVerifikasi::Ditolak->value])],
            'alasan_penolakan' => [
                Rule::requiredIf(fn () => $this->input('status_verifikasi') === StatusVerifikasi::Ditolak->value),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status_verifikasi.in' => 'Keputusan verifikasi harus "diterima" atau "ditolak".',
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi agar unit tahu apa yang harus diperbaiki.',
        ];
    }

    public function keputusan(): StatusVerifikasi
    {
        return StatusVerifikasi::from($this->validated('status_verifikasi'));
    }

    public function alasan(): ?string
    {
        return $this->keputusan() === StatusVerifikasi::Ditolak
            ? trim((string) $this->validated('alasan_penolakan'))
            : null;
    }
}
