<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Role;
use App\Models\Skill;
use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvaluationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    /**
     * Test unauthenticated access returns 401 on all evaluation endpoints.
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/evaluations')->assertStatus(401);
        $this->getJson('/api/evaluations/1')->assertStatus(401);
        $this->patchJson('/api/evaluations/1/note', ['note' => 'test'])->assertStatus(401);
        $this->patchJson('/api/evaluations/1/override', ['final_role_id' => 1])->assertStatus(401);
        $this->postJson('/api/evaluations/1/reset')->assertStatus(401);
        $this->postJson('/api/evaluations/lock-all')->assertStatus(401);
    }

    /**
     * Test student and assistant roles cannot access evaluation endpoints (403).
     */
    public function test_student_and_assistant_cannot_access_evaluations(): void
    {
        $student = User::factory()->student()->create();
        $assistant = User::factory()->assistant()->create();

        foreach ([$student, $assistant] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/evaluations')->assertStatus(403);
            $this->getJson('/api/evaluations/1')->assertStatus(403);
            $this->patchJson('/api/evaluations/1/note', ['note' => 'test'])->assertStatus(403);
            $this->patchJson('/api/evaluations/1/override', ['final_role_id' => 1])->assertStatus(403);
            $this->postJson('/api/evaluations/1/reset')->assertStatus(403);
            $this->postJson('/api/evaluations/lock-all')->assertStatus(403);
        }
    }

    /**
     * Test practitioner can access read and note endpoints, but NOT override, reset, or lock-all.
     */
    public function test_practitioner_access_restrictions(): void
    {
        $practitioner = User::factory()->practitioner()->create();
        $student = User::factory()->student()->create();

        Sanctum::actingAs($practitioner);

        // Allowed for practitioner
        $this->getJson('/api/evaluations')->assertStatus(200);
        $this->getJson("/api/evaluations/{$student->id}")->assertStatus(200);
        $this->patchJson("/api/evaluations/{$student->id}/note", ['note' => 'Portofolio bagus'])->assertStatus(200);

        // Forbidden for practitioner
        $this->patchJson("/api/evaluations/{$student->id}/override", ['final_role_id' => 1])->assertStatus(403);
        $this->postJson("/api/evaluations/{$student->id}/reset")->assertStatus(403);
        $this->postJson('/api/evaluations/lock-all')->assertStatus(403);
    }

    /**
     * Test lecturer can access all 6 evaluation endpoints.
     */
    public function test_lecturer_can_access_all_endpoints(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleBe = Role::where('code', 'BE')->firstOrFail();

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $rolePm->id,
            'recommended_score' => 80.0,
            'final_role_id' => $rolePm->id,
        ]);

        Sanctum::actingAs($lecturer);

        $this->getJson('/api/evaluations')->assertStatus(200);
        $this->getJson("/api/evaluations/{$student->id}")->assertStatus(200);
        $this->patchJson("/api/evaluations/{$student->id}/note", ['note' => 'Catatan dosen'])->assertStatus(200);
        $this->patchJson("/api/evaluations/{$student->id}/override", ['final_role_id' => $roleBe->id])->assertStatus(200);
        $this->postJson("/api/evaluations/{$student->id}/reset")->assertStatus(200);
        $this->postJson('/api/evaluations/lock-all')->assertStatus(200);
    }

    /**
     * Test GET /api/evaluations with data recap and complaints flag.
     */
    public function test_get_evaluations_recap_table_with_complaints_flag(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student1 = User::factory()->student()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@mail.ugm.ac.id',
        ]);
        $student2 = User::factory()->student()->create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti.nurhaliza@mail.ugm.ac.id',
        ]);

        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleUiux = Role::where('code', 'UIUX')->firstOrFail();

        StudentProfile::create([
            'user_id' => $student1->id,
            'nim' => '21/111111/SV/11111',
            'role_1_id' => $rolePm->id,
            'role_2_id' => $roleUiux->id,
        ]);

        StudentRoleEvaluation::create([
            'user_id' => $student1->id,
            'score_role_1' => 70.0,
            'score_role_2' => 85.0,
            'recommended_role_id' => $roleUiux->id,
            'recommended_score' => 85.0,
            'final_role_id' => $roleUiux->id,
            'is_overridden' => false,
            'is_locked' => false,
        ]);

        // Create a team and a complaint against student 1
        $team = Team::create(['team_type' => 'internal']);
        Complaint::create([
            'reporter_id' => $student2->id,
            'reported_id' => $student1->id,
            'team_id' => $team->id,
            'description' => 'Tidak hadir rapat kelompok dan tidak mengerjakan tugas.',
            'evidence_file_path' => 'complaints/evidence-1.pdf',
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->getJson('/api/evaluations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'user_id',
                        'name',
                        'email',
                        'nim',
                        'is_bypass',
                        'role_1',
                        'role_2',
                        'score_role_1',
                        'score_role_2',
                        'recommended_role_id',
                        'recommended_role',
                        'recommended_score',
                        'final_role_id',
                        'final_role',
                        'is_overridden',
                        'is_locked',
                        'has_complaints',
                        'evaluation_note',
                        'updated_by',
                    ],
                ],
                'meta',
            ]);

        // Student 1 has complaints, Student 2 does not
        $data = collect($response->json('data'));
        $budi = $data->firstWhere('user_id', $student1->id);
        $siti = $data->firstWhere('user_id', $student2->id);

        $this->assertNotNull($budi);
        $this->assertTrue($budi['has_complaints']);
        $this->assertEquals('21/111111/SV/11111', $budi['nim']);
        $this->assertEquals(85.0, $budi['recommended_score']);

        $this->assertNotNull($siti);
        $this->assertFalse($siti['has_complaints']);
    }

    /**
     * Test GET /api/evaluations filtering by search, role, and boolean flags.
     */
    public function test_get_evaluations_filtering(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleFe = Role::where('code', 'FE')->firstOrFail();

        $student1 = User::factory()->student()->create(['name' => 'Alfa Romeo']);
        StudentProfile::create(['user_id' => $student1->id, 'nim' => '21/100000/SV/00001']);
        StudentRoleEvaluation::create([
            'user_id' => $student1->id,
            'final_role_id' => $rolePm->id,
            'is_overridden' => true,
            'is_locked' => false,
        ]);

        $student2 = User::factory()->student()->create(['name' => 'Beta Juliet']);
        StudentProfile::create(['user_id' => $student2->id, 'nim' => '21/200000/SV/00002']);
        StudentRoleEvaluation::create([
            'user_id' => $student2->id,
            'final_role_id' => $roleFe->id,
            'is_overridden' => false,
            'is_locked' => true,
        ]);

        Sanctum::actingAs($lecturer);

        // Filter search
        $resSearch = $this->getJson('/api/evaluations?search=Alfa');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals($student1->id, $resSearch->json('data.0.user_id'));

        // Filter role by code
        $resRole = $this->getJson('/api/evaluations?role=FE');
        $resRole->assertStatus(200);
        $this->assertCount(1, $resRole->json('data'));
        $this->assertEquals($student2->id, $resRole->json('data.0.user_id'));

        // Filter is_overridden = true
        $resOverridden = $this->getJson('/api/evaluations?is_overridden=true');
        $resOverridden->assertStatus(200);
        $this->assertCount(1, $resOverridden->json('data'));
        $this->assertEquals($student1->id, $resOverridden->json('data.0.user_id'));

        // Filter is_locked = true
        $resLocked = $this->getJson('/api/evaluations?is_locked=true');
        $resLocked->assertStatus(200);
        $this->assertCount(1, $resLocked->json('data'));
        $this->assertEquals($student2->id, $resLocked->json('data.0.user_id'));
    }

    /**
     * Test GET /api/evaluations/{userId} detail modal.
     */
    public function test_get_evaluation_detail_modal(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create([
            'name' => 'Dewi Sartika',
            'email' => 'dewi@mail.ugm.ac.id',
        ]);

        $roleUiux = Role::where('code', 'UIUX')->firstOrFail();
        $uiuxSkill = Skill::where('role_id', $roleUiux->id)->firstOrFail();

        $profile = StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '21/333333/SV/33333',
            'role_1_id' => $roleUiux->id,
            'portfolio_link' => 'https://figma.com/@dewi',
        ]);
        $profile->skills()->attach($uiuxSkill->id);

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $roleUiux->id,
            'recommended_score' => 90.0,
            'final_role_id' => $roleUiux->id,
            'evaluation_note' => 'Portofolio UIUX sangat komprehensif.',
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->getJson("/api/evaluations/{$student->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'student' => [
                        'user_id' => $student->id,
                        'name' => 'Dewi Sartika',
                        'nim' => '21/333333/SV/33333',
                        'portfolio_link' => 'https://figma.com/@dewi',
                    ],
                    'evaluation' => [
                        'recommended_score' => 90.0,
                        'evaluation_note' => 'Portofolio UIUX sangat komprehensif.',
                    ],
                    'checked_skill_ids' => [$uiuxSkill->id],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'skills_by_role' => [
                        '*' => ['role_id', 'role_code', 'role_name', 'total_skills', 'checked_count', 'skills'],
                    ],
                    'complaints',
                ],
            ]);
    }

    /**
     * Test PATCH /api/evaluations/{userId}/note.
     */
    public function test_update_evaluation_note(): void
    {
        $practitioner = User::factory()->practitioner()->create();
        $student = User::factory()->student()->create();

        Sanctum::actingAs($practitioner);

        $response = $this->patchJson("/api/evaluations/{$student->id}/note", [
            'note' => 'Rekomendasi dari praktisi: sangat cocok di posisi Back-End.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_id' => $student->id,
                    'evaluation_note' => 'Rekomendasi dari praktisi: sangat cocok di posisi Back-End.',
                    'updated_by' => [
                        'id' => $practitioner->id,
                        'name' => $practitioner->name,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('student_role_evaluations', [
            'user_id' => $student->id,
            'evaluation_note' => 'Rekomendasi dari praktisi: sangat cocok di posisi Back-End.',
            'updated_by' => $practitioner->id,
        ]);
    }

    /**
     * Test PATCH /api/evaluations/{userId}/override updates final role and sets is_overridden = true.
     */
    public function test_override_final_role_by_lecturer(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleBe = Role::where('code', 'BE')->firstOrFail();

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $rolePm->id,
            'final_role_id' => $rolePm->id,
            'is_overridden' => false,
            'is_locked' => false,
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->patchJson("/api/evaluations/{$student->id}/override", [
            'final_role_id' => $roleBe->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_id' => $student->id,
                    'final_role_id' => $roleBe->id,
                    'final_role' => [
                        'id' => $roleBe->id,
                        'code' => 'BE',
                    ],
                    'is_overridden' => true,
                    'updated_by' => [
                        'id' => $lecturer->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('student_role_evaluations', [
            'user_id' => $student->id,
            'final_role_id' => $roleBe->id,
            'is_overridden' => true,
            'updated_by' => $lecturer->id,
        ]);
    }

    /**
     * Test PATCH /api/evaluations/{userId}/override is rejected if locked.
     */
    public function test_override_is_rejected_when_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        $roleBe = Role::where('code', 'BE')->firstOrFail();

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'final_role_id' => 1,
            'is_locked' => true,
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->patchJson("/api/evaluations/{$student->id}/override", [
            'final_role_id' => $roleBe->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Evaluasi peran mahasiswa telah dikunci dan tidak dapat di-override.',
            ]);
    }

    /**
     * Test POST /api/evaluations/{userId}/reset resets final role to recommended role.
     */
    public function test_reset_final_role_to_recommended(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();
        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleBe = Role::where('code', 'BE')->firstOrFail();

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $rolePm->id,
            'final_role_id' => $roleBe->id,
            'is_overridden' => true,
            'is_locked' => false,
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->postJson("/api/evaluations/{$student->id}/reset");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_id' => $student->id,
                    'final_role_id' => $rolePm->id,
                    'is_overridden' => false,
                    'updated_by' => [
                        'id' => $lecturer->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('student_role_evaluations', [
            'user_id' => $student->id,
            'final_role_id' => $rolePm->id,
            'is_overridden' => false,
            'updated_by' => $lecturer->id,
        ]);
    }

    /**
     * Test POST /api/evaluations/{userId}/reset is rejected if locked.
     */
    public function test_reset_is_rejected_when_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student = User::factory()->student()->create();

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => 1,
            'final_role_id' => 2,
            'is_overridden' => true,
            'is_locked' => true,
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->postJson("/api/evaluations/{$student->id}/reset");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Evaluasi peran mahasiswa telah dikunci dan tidak dapat direset.',
            ]);
    }

    /**
     * Test POST /api/evaluations/lock-all locks all student evaluations.
     */
    public function test_lock_all_student_role_evaluations(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();

        StudentRoleEvaluation::create([
            'user_id' => $student1->id,
            'is_locked' => false,
        ]);
        StudentRoleEvaluation::create([
            'user_id' => $student2->id,
            'is_locked' => false,
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->postJson('/api/evaluations/lock-all');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Seluruh evaluasi peran mahasiswa berhasil dikunci.',
                'data' => [
                    'affected_rows' => 2,
                    'is_locked' => true,
                ],
            ]);

        $this->assertEquals(0, StudentRoleEvaluation::where('is_locked', false)->count());
        $this->assertEquals(2, StudentRoleEvaluation::where('is_locked', true)->count());
    }

    /**
     * Test /api/v1/evaluations versioned route aliases.
     */
    public function test_v1_versioned_routes(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/v1/evaluations')->assertStatus(200);
        $this->postJson('/api/v1/evaluations/lock-all')->assertStatus(200);
    }
}
