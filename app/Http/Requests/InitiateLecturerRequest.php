<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InitiateLecturerRequest extends FormRequest
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
        if ($this->has('lecturers')) {
            return [
                'lecturers' => ['required', 'array', 'min:1'],
                'lecturers.*.email' => ['required', 'email', 'max:128'],
                'lecturers.*.name' => ['nullable', 'string', 'max:128'],
            ];
        }

        return [
            'email' => ['required', 'email', 'max:128'],
            'name' => ['nullable', 'string', 'max:128'],
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
            'email.required' => 'Bidang email dosen pengampu wajib diisi.',
            'email.email' => 'Format email dosen pengampu tidak valid.',
            'lecturers.required' => 'Daftar dosen pengampu wajib disediakan.',
            'lecturers.*.email.required' => 'Setiap dosen pengampu wajib memiliki alamat email yang valid.',
        ];
    }
}
