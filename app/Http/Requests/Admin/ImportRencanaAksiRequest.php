<?php

namespace App\Http\Requests\Admin;

use App\Models\RencanaAksi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RencanaAksi::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['xlsx', 'xls'])->max(10 * 1024),
            ],
        ];
    }
}
