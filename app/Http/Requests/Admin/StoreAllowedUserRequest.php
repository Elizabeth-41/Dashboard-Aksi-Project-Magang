<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllowedUserRequest extends FormRequest
{
    /**
     * Otorisasi role ditangani middleware `role:admin_pusat` pada rute.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('allowed_users', 'email')],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            // Semua akun wajib terikat unit. Untuk Admin Pusat unit hanya penanda
            // asal instansi; cakupannya tetap seluruh unit (lihat User::cakupanDinasIds).
            'dinas_id' => ['required', 'integer', Rule::exists('dinas', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array{email: string, role: UserRole, dinas_id: int, name: ?string}
     */
    public function payload(): array
    {
        return [
            'email' => $this->validated('email'),
            'name' => $this->validated('name'),
            'role' => UserRole::from($this->validated('role')),
            'dinas_id' => (int) $this->validated('dinas_id'),
        ];
    }
}
