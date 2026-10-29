<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStakeholderRequest extends FormRequest
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

        if ($this->has('stakeholders')) {
            return [
                'stakeholders' => ['required', 'array', 'min:1'],
                'stakeholders.*.email' => ['required', 'email', 'max:128'],
                'stakeholders.*.role' => ['required', $roleValidator],
                'stakeholders.*.name' => ['nullable', 'string', 'max:128'],
                'stakeholders.*.nim' => ['nullable', 'string', 'max:50'],
                'stakeholders.*.is_bypass' => ['nullable', 'boolean'],
            ];
        }

        return [
            'email' => ['required', 'email', 'max:128'],
            'role' => ['required', $roleValidator],
            'name' => ['nullable', 'string', 'max:128'],
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
            'email.required' => 'Bidang email stakeholder wajib diisi.',
            'email.email' => 'Format email stakeholder tidak valid.',
            'role.required' => 'Bidang peran (role) stakeholder wajib ditentukan.',
            'stakeholders.required' => 'Daftar stakeholders wajib disediakan.',
            'stakeholders.*.email.required' => 'Setiap stakeholder wajib memiliki alamat email yang valid.',
            'stakeholders.*.role.required' => 'Setiap stakeholder wajib memiliki peran yang ditentukan.',
        ];
    }
}
