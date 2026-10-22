<?php

namespace App\Http\Requests;

use App\Enums\Dimensi;
use App\Models\Dinas;
use App\Reports\LaporanCapaian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaporanRequest extends FormRequest
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
            'tahun' => ['nullable', 'array'],
            'tahun.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
            'dinas' => ['nullable', 'string', Rule::exists('dinas', 'slug')],
            'dimensi' => ['nullable', Rule::enum(Dimensi::class)],
            'format' => [
                Rule::requiredIf(fn () => $this->routeIs('api.laporan.export')),
                Rule::in(['pdf', 'xlsx']),
            ],
        ];
    }

    public function laporan(): LaporanCapaian
    {
        return new LaporanCapaian(
            tahun: array_values(array_map('intval', $this->validated('tahun') ?? [])),
            dinasId: $this->filled('dinas') ? Dinas::query()->where('slug', $this->validated('dinas'))->value('id') : null,
            dimensi: $this->filled('dimensi') ? Dimensi::from($this->validated('dimensi')) : null,
        );
    }
}
