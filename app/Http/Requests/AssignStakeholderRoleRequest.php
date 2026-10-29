<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignStakeholderRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        $roleValidator = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || UserRole::normalize($value) === null) {
                $fail('Peran (role) "'.$value.'" tidak valid. Pilihan yang valid: mahasiswa/student, praktisi/practitioner, asisten/assistant, dosen/lecturer, admin.');
            }
        };

        return [
            'email' => ['nullable', 'email', 'max:128'],
            'role' => ['required', $roleValidator],
            'nim' => ['nullable', 'string', 'max:50'],
            'is_bypass' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Bidang peran (role) stakeholder wajib ditentukan.',
        ];
    }
}
