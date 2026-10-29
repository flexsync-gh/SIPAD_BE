<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeholderManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test initiating a lecturer email as full admin.
     */
    public function test_can_initiate_single_lecturer_as_full_admin(): void
    {
        $payload = [
            'email' => 'dinar@ugm.ac.id',
            'name' => 'Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.',
        ];

        $response = $this->postJson('/api/v1/admin/initiate-lecturer', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'dinar@ugm.ac.id',
                    'name' => 'Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.',
                    'user_role' => 'admin',
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'dinar@ugm.ac.id',
            'user_role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Test initiating multiple lecturers in bulk as full admin.
     */
    public function test_can_initiate_bulk_lecturers_as_full_admin(): void
    {
        $payload = [
            'lecturers' => [
                [
                    'email' => 'dinar@ugm.ac.id',
                    'name' => 'Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.',
                ],
                [
                    'email' => 'iwan@ugm.ac.id',
                    'name' => 'Ahmad Iwan Fadli, S.Kom., M.Eng.',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/admin/initiate-lecturer', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'dinar@ugm.ac.id', 'user_role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'iwan@ugm.ac.id', 'user_role' => 'admin']);
    }

    /**
     * Test validation failure on lecturer initiation when email is missing or invalid.
     */
    public function test_initiate_lecturer_validation_fails_on_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/admin/initiate-lecturer', [
            'email' => 'invalid-email-format',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test inputting a student stakeholder with Indonesian role alias and bypass flag.
     */
    public function test_can_input_student_stakeholder_with_indonesian_alias_and_bypass(): void
    {
        $payload = [
            'email' => 'mahasiswa1@mail.ugm.ac.id',
            'name' => 'Budi Santoso',
            'role' => 'mahasiswa',
            'nim' => '23/512345/SV/22001',
            'is_bypass' => true,
        ];

        $response = $this->postJson('/api/v1/stakeholders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'mahasiswa1@mail.ugm.ac.id',
                    'user_role' => 'student',
                    'student_profile' => [
                        'nim' => '23/512345/SV/22001',
                        'is_bypass' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mahasiswa1@mail.ugm.ac.id',
            'user_role' => 'student',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'nim' => '23/512345/SV/22001',
            'is_bypass' => 1,
        ]);
    }

    /**
     * Test inputting practitioner and assistant stakeholders.
     */
    public function test_can_input_practitioner_and_assistant_stakeholders(): void
    {
        // Practitioner
        $responsePractitioner = $this->postJson('/api/v1/stakeholders', [
            'email' => 'mentor@industry.com',
            'name' => 'Praktisi Tech',
            'role' => 'praktisi',
        ]);

        $responsePractitioner->assertStatus(201)
            ->assertJson([
                'data' => [
                    'email' => 'mentor@industry.com',
                    'user_role' => 'practitioner',
                ],
            ]);

        // Assistant
        $responseAssistant = $this->postJson('/api/v1/stakeholders', [
            'email' => 'asprak@mail.ugm.ac.id',
            'name' => 'Asisten Lab',
            'role' => 'asprak',
        ]);

        $responseAssistant->assertStatus(201)
            ->assertJson([
                'data' => [
                    'email' => 'asprak@mail.ugm.ac.id',
                    'user_role' => 'assistant',
                ],
            ]);
    }

    /**
     * Test bulk input of stakeholders.
     */
    public function test_can_input_bulk_stakeholders(): void
    {
        $payload = [
            'stakeholders' => [
                [
                    'email' => 'mhs1@mail.ugm.ac.id',
                    'name' => 'Mahasiswa 1',
                    'role' => 'mahasiswa',
                ],
                [
                    'email' => 'mhs2@mail.ugm.ac.id',
                    'name' => 'Mahasiswa 2 (GEMASTIK)',
                    'role' => 'student',
                    'is_bypass' => true,
                ],
                [
                    'email' => 'asprak1@mail.ugm.ac.id',
                    'name' => 'Asprak 1',
                    'role' => 'asisten',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/stakeholders', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['email' => 'mhs1@mail.ugm.ac.id', 'user_role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'mhs2@mail.ugm.ac.id', 'user_role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'asprak1@mail.ugm.ac.id', 'user_role' => 'assistant']);
    }

    /**
     * Test assigning role to stakeholder by user ID.
     */
    public function test_can_assign_role_by_user_id(): void
    {
        $user = User::factory()->create([
            'email' => 'candidate@mail.ugm.ac.id',
            'user_role' => UserRole::Student,
        ]);

        $response = $this->putJson("/api/v1/stakeholders/{$user->id}/role", [
            'role' => 'asisten',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'user_role' => 'assistant',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'user_role' => 'assistant',
        ]);
    }

    /**
     * Test assigning role to stakeholder by email.
     */
    public function test_can_assign_role_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'someone@mail.ugm.ac.id',
            'user_role' => UserRole::Assistant,
        ]);

        $response = $this->postJson('/api/v1/stakeholders/assign-role', [
            'email' => 'someone@mail.ugm.ac.id',
            'role' => 'mahasiswa',
            'nim' => '24/123456/SV/99999',
            'is_bypass' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'someone@mail.ugm.ac.id',
                    'user_role' => 'student',
                    'student_profile' => [
                        'nim' => '24/123456/SV/99999',
                        'is_bypass' => false,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'someone@mail.ugm.ac.id',
            'user_role' => 'student',
        ]);
    }

    /**
     * Test listing stakeholders with role filtering.
     */
    public function test_can_list_stakeholders_filtered_by_role(): void
    {
        User::factory()->count(3)->create(['user_role' => UserRole::Student]);
        User::factory()->count(2)->create(['user_role' => UserRole::Assistant]);
        User::factory()->create(['user_role' => UserRole::Practitioner]);

        $response = $this->getJson('/api/v1/stakeholders?role=student');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 3,
                ],
            ]);
    }
}
