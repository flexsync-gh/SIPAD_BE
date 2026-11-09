<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NimFormat implements ValidationRule
{
    /**
     * Regex pattern for valid NIM:
     * (2 digit angka)/(6 digit angka)/(2 huruf)/(5 digit angka)
     * Contoh: 12/123456/SV/12345
     */
    public const PATTERN = '/^\d{2}\/\d{6}\/[A-Za-z]{2}\/\d{5}$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Format NIM tidak valid. Format yang benar: 12/123456/SV/12345 (2 digit angka/6 digit angka/2 huruf/5 digit angka).');
        }
    }
}
