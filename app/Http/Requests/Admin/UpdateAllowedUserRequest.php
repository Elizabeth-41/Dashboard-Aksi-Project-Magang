<?php

namespace App\Http\Requests\Admin;

use App\Enums\AllowedUserStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllowedUserRequest extends FormRequest
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
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'dinas_id' => ['sometimes', 'required', 'integer', Rule::exists('dinas', 'id')],
            'status' => ['sometimes', 'required', Rule::enum(AllowedUserStatus::class)],
        ];
    }

    /**
     * @return array{role?: UserRole, dinas_id?: int, status?: AllowedUserStatus}
     */
    public function payload(): array
    {
        $payload = [];

        if ($this->has('role')) {
            $payload['role'] = UserRole::from($this->validated('role'));
        }

        if ($this->has('dinas_id')) {
            $payload['dinas_id'] = (int) $this->validated('dinas_id');
        }

        if ($this->has('status')) {
            $payload['status'] = AllowedUserStatus::from($this->validated('status'));
        }

        return $payload;
    }
}
