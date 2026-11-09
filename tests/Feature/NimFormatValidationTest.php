<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NimFormatValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    /**
     * Test valid NIM formats are accepted in student profiling.
     */
    public function test_student_profiling_accepts_valid_nim(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $role = Role::firstOrFail();

        $validNims = [
            '12/123456/SV/12345',
            '21/473829/SV/19283',
            '23/512345/TK/22001',
        ];

        foreach ($validNims as $validNim) {
            $response = $this->postJson('/api/student/profile', [
                'nim' => $validNim,
                'role_1_id' => $role->id,
            ]);

            $response->assertStatus(200);
            $this->assertDatabaseHas('student_profiles', [
                'user_id' => $student->id,
                'nim' => $validNim,
            ]);
        }
    }

    /**
     * Test invalid NIM formats are rejected (422) in student profiling.
     */
    public function test_student_profiling_rejects_invalid_nim(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $role = Role::firstOrFail();

        $invalidNims = [
            '1/123456/SV/12345',   // Only 1 digit at start
            '123/123456/SV/12345', // 3 digits at start
            '12/12345/SV/12345',   // 5 digits in second part
            '12/1234567/SV/12345', // 7 digits in second part
            '12/123456/S/12345',   // 1 letter in faculty
            '12/123456/SVK/12345', // 3 letters in faculty
            '12/123456/12/12345',  // digits instead of letters in faculty
            '12/123456/SV/1234',   // 4 digits at end
            '12/123456/SV/123456', // 6 digits at end
            '12-123456-SV-12345',  // dashes instead of slashes
            'plain-string',
        ];

        foreach ($invalidNims as $invalidNim) {
            $response = $this->postJson('/api/student/profile', [
                'nim' => $invalidNim,
                'role_1_id' => $role->id,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['nim']);
        }
    }

    /**
     * Test whitelist manual input rejects invalid NIM.
     */
    public function test_whitelist_manual_input_rejects_invalid_nim(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->postJson('/api/users/whitelist', [
            'email' => 'mhs.baru@mail.ugm.ac.id',
            'name' => 'Mahasiswa Baru',
            'role' => 'student',
            'nim' => 'invalid-nim-format',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nim']);
    }

    /**
     * Test stakeholder store request rejects invalid NIM.
     */
    public function test_stakeholder_store_rejects_invalid_nim(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->postJson('/api/stakeholders', [
            'email' => 'mhs.stakeholder@mail.ugm.ac.id',
            'role' => 'student',
            'nim' => '12/12345/SV/12345', // only 5 digits in middle
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nim']);
    }

    /**
     * Test assigning role with invalid NIM is rejected.
     */
    public function test_assign_role_rejects_invalid_nim(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->putJson("/api/stakeholders/{$student->id}/role", [
            'role' => 'student',
            'nim' => '12/123456/S/12345', // only 1 letter in faculty
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nim']);
    }

    /**
     * Test model direct creation/saving rejects invalid NIM.
     */
    public function test_student_profile_model_saving_rejects_invalid_nim(): void
    {
        $student = User::factory()->student()->create();

        $this->expectException(ValidationException::class);

        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => 'salah/format/nim/123',
        ]);
    }
}
