<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KrsWhitelistImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /**
     * @var list<User>
     */
    protected array $importedUsers = [];

    /**
     * @var list<array{row: int, error: string, data: array<string, mixed>}>
     */
    protected array $errors = [];

    /**
     * Process collection of rows.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $rowIndex = 1; // Row 1 is heading

        foreach ($rows as $row) {
            $rowIndex++;
            $data = $row->toArray();

            // Extract email from multiple possible headers
            $email = null;
            foreach (['email', 'e_mail', 'mail', 'alamat_email', 'email_ugm', 'email_mahasiswa'] as $key) {
                if (! empty($data[$key])) {
                    $email = strtolower(trim((string) $data[$key]));
                    break;
                }
            }

            // Extract NIM from multiple possible headers
            $nim = null;
            foreach (['nim', 'no_mhs', 'nomor_induk_mahasiswa', 'student_id', 'npm'] as $key) {
                if (! empty($data[$key])) {
                    $nim = trim((string) $data[$key]);
                    break;
                }
            }

            // Extract name from multiple possible headers
            $name = null;
            foreach (['name', 'nama', 'nama_mahasiswa', 'nama_lengkap', 'fullname'] as $key) {
                if (! empty($data[$key])) {
                    $name = trim((string) $data[$key]);
                    break;
                }
            }

            // Fallback for unkeyed / position-based columns
            if (! $email && isset($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                $email = strtolower(trim((string) $data[0]));
                $nim = isset($data[1]) ? trim((string) $data[1]) : null;
                $name = isset($data[2]) ? trim((string) $data[2]) : null;
            }

            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = [
                    'row' => $rowIndex,
                    'error' => 'Format email tidak valid atau kolom email kosong.',
                    'data' => $data,
                ];

                continue;
            }

            DB::transaction(function () use ($email, $name, $nim) {
                $userAttributes = [
                    'user_role' => UserRole::Student,
                    'status' => 'active',
                ];

                if (! empty($name)) {
                    $userAttributes['name'] = $name;
                }

                $user = User::updateOrCreate(
                    ['email' => $email],
                    $userAttributes
                );

                $profileAttributes = [
                    'is_bypass' => false,
                ];

                if (! empty($nim)) {
                    $profileAttributes['nim'] = $nim;
                }

                StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $profileAttributes
                );

                $this->importedUsers[] = $user;
            });
        }
    }

    /**
     * Get the count of successfully imported users.
     */
    public function getImportedCount(): int
    {
        return count($this->importedUsers);
    }

    /**
     * Get the imported User instances.
     *
     * @return list<User>
     */
    public function getImportedUsers(): array
    {
        return $this->importedUsers;
    }

    /**
     * Get errors encountered during import.
     *
     * @return list<array{row: int, error: string, data: array<string, mixed>}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
