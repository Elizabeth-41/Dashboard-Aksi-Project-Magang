<?php

namespace App\Http\Requests;

use App\Enums\StatusKegiatan;
use App\Models\RencanaAksi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRencanaAksiStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RencanaAksi $rencanaAksi */
        $rencanaAksi = $this->route('rencana_aksi');

        return $this->user()?->can('updateStatus', $rencanaAksi) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(StatusKegiatan::class)],
        ];
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::from($this->validated('status'));
    }
}
