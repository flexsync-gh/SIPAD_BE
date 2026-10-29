<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Lecturer = 'lecturer';
    case Admin = 'admin';
    case Assistant = 'assistant';
    case Practitioner = 'practitioner';

    /**
     * Normalize input role string (supports both Indonesian and English aliases).
     */
    public static function normalize(string $input): ?string
    {
        $normalized = strtolower(trim($input));

        return match ($normalized) {
            'student', 'mahasiswa' => self::Student->value,
            'lecturer', 'dosen', 'dosen_pengampu' => self::Lecturer->value,
            'admin', 'full_admin', 'master_admin', 'full admin' => self::Admin->value,
            'assistant', 'asisten', 'asprak', 'asisten_praktikum', 'asisten praktikum' => self::Assistant->value,
            'practitioner', 'praktisi', 'praktisi_industri', 'praktisi industri' => self::Practitioner->value,
            default => null,
        };
    }

    /**
     * Get human-readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Mahasiswa',
            self::Lecturer => 'Dosen Pengampu',
            self::Admin => 'Admin (Full)',
            self::Assistant => 'Asisten Praktikum',
            self::Practitioner => 'Praktisi Industri',
        };
    }
}
