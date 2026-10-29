<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Project;
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

class StudentProfilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    /**
     * Test unauthenticated access returns 401.
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/student/profiling-config')->assertStatus(401);
        $this->postJson('/api/student/profile', [])->assertStatus(401);
        $this->getJson('/api/student/my-team')->assertStatus(401);
    }

    /**
     * Test non-student role returns 403 forbidden.
     */
    public function test_non_student_role_returns_403(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/student/profiling-config')->assertStatus(403);
        $this->postJson('/api/student/profile', [
            'nim' => '21/123456/TK/12345',
            'role_1_id' => 1,
        ])->assertStatus(403);
        $this->getJson('/api/student/my-team')->assertStatus(403);
    }

    /**
     * Test GET /api/student/profiling-config for regular student with optional role 2.
     */
    public function test_get_profiling_config_for_regular_student_with_optional_role_2(): void
    {
        AppSetting::updateOrCreate(['key' => 'is_role_2_required'], ['value' => 'false']);

        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '21/473829/SV/19283',
            'is_bypass' => false,
        ]);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/profiling-config');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'config' => [
                        'is_bypass' => false,
                        'is_role_2_required' => false,
                        'allow_role_2' => true,
                        'role_2_status' => 'optional',
                        'is_locked' => false,
                    ],
                    'current_profile' => [
                        'nim' => '21/473829/SV/19283',
                        'is_bypass' => false,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'config',
                    'current_profile',
                    'roles' => [
                        '*' => ['id', 'code', 'name', 'skills'],
                    ],
                ],
            ]);
    }

    /**
     * Test GET /api/student/profiling-config for regular student with mandatory role 2.
     */
    public function test_get_profiling_config_for_regular_student_with_mandatory_role_2(): void
    {
        AppSetting::updateOrCreate(['key' => 'is_role_2_required'], ['value' => 'true']);

        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/profiling-config');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'config' => [
                        'is_bypass' => false,
                        'is_role_2_required' => true,
                        'allow_role_2' => true,
                        'role_2_status' => 'required',
                        'is_locked' => false,
                    ],
                ],
            ]);
    }

    /**
     * Test GET /api/student/profiling-config for bypass student (forces role 2 disabled).
     */
    public function test_get_profiling_config_for_bypass_student(): void
    {
        AppSetting::updateOrCreate(['key' => 'is_role_2_required'], ['value' => 'true']);

        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '21/111111/SV/11111',
            'is_bypass' => true,
        ]);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/profiling-config');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'config' => [
                        'is_bypass' => true,
                        'is_role_2_required' => false,
                        'allow_role_2' => false,
                        'role_2_status' => 'disabled',
                    ],
                ],
            ]);
    }

    /**
     * Test POST /api/student/profile validation rejects duplicate roles.
     */
    public function test_store_profile_rejects_duplicate_roles(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $rolePm = Role::where('code', 'PM')->firstOrFail();

        $response = $this->postJson('/api/student/profile', [
            'nim' => '21/473829/SV/19283',
            'role_1_id' => $rolePm->id,
            'role_2_id' => $rolePm->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Pilihan role 1 dan role 2 tidak boleh sama.',
            ]);
    }

    /**
     * Test POST /api/student/profile rejects modification if evaluation is locked.
     */
    public function test_store_profile_rejects_if_evaluation_is_locked(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => 1,
            'final_role_id' => 1,
            'is_locked' => true,
        ]);

        $response = $this->postJson('/api/student/profile', [
            'nim' => '21/473829/SV/19283',
            'role_1_id' => 1,
            'role_2_id' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Form profiling telah dikunci oleh dosen pengampu dan tidak dapat diubah.',
            ]);
    }

    /**
     * Test POST /api/student/profile rejects missing role 2 when required by settings.
     */
    public function test_store_profile_rejects_missing_role_2_when_required(): void
    {
        AppSetting::updateOrCreate(['key' => 'is_role_2_required'], ['value' => 'true']);

        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/student/profile', [
            'nim' => '21/473829/SV/19283',
            'role_1_id' => 1,
            'role_2_id' => null,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Pilihan role ke-2 wajib diisi sesuai pengaturan sistem.',
            ]);
    }

    /**
     * Test POST /api/student/profile forces role_2_id to null for bypass students.
     */
    public function test_store_profile_forces_null_role_2_for_bypass_student(): void
    {
        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '21/111111/SV/11111',
            'is_bypass' => true,
        ]);
        Sanctum::actingAs($student);

        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleUiux = Role::where('code', 'UIUX')->firstOrFail();

        $response = $this->postJson('/api/student/profile', [
            'nim' => '21/111111/SV/11111',
            'role_1_id' => $rolePm->id,
            'role_2_id' => $roleUiux->id,
            'portfolio_link' => 'https://github.com/bypass-student',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'role_1_id' => $rolePm->id,
            'role_2_id' => null,
            'portfolio_link' => 'https://github.com/bypass-student',
        ]);
    }

    /**
     * Test POST /api/student/profile successfully saves and computes scoring engine recommendations.
     */
    public function test_store_profile_computes_scoring_engine(): void
    {
        AppSetting::updateOrCreate(['key' => 'is_role_2_required'], ['value' => 'false']);

        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $roleFe = Role::where('code', 'FE')->firstOrFail();
        $roleBe = Role::where('code', 'BE')->firstOrFail();

        // FE has 5 seeded skills: pick 3 (3/5 = 60% -> 60% * 0.70 = 42 + 30 priority = 72.0)
        $feSkills = Skill::where('role_id', $roleFe->id)->take(3)->pluck('id')->toArray();

        // BE has 5 seeded skills: pick all 5 (5/5 = 100% -> 100% * 0.70 = 70 + 15 priority = 85.0)
        $beSkills = Skill::where('role_id', $roleBe->id)->pluck('id')->toArray();

        $selectedSkillIds = array_merge($feSkills, $beSkills);

        $response = $this->postJson('/api/student/profile', [
            'nim' => '21/473829/SV/19283',
            'role_1_id' => $roleFe->id,
            'role_2_id' => $roleBe->id,
            'skill_ids' => $selectedSkillIds,
            'portfolio_link' => 'https://linkedin.com/in/student-fe-be',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'profile' => [
                        'nim' => '21/473829/SV/19283',
                        'role_1_id' => $roleFe->id,
                        'role_2_id' => $roleBe->id,
                        'portfolio_link' => 'https://linkedin.com/in/student-fe-be',
                    ],
                    'evaluation' => [
                        'score_role_1' => 72.0,
                        'score_role_2' => 85.0,
                        'recommended_role_id' => $roleBe->id,
                        'recommended_score' => 85.0,
                        'final_role_id' => $roleBe->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('student_role_evaluations', [
            'user_id' => $student->id,
            'score_role_1' => 72.0,
            'score_role_2' => 85.0,
            'recommended_role_id' => $roleBe->id,
            'final_role_id' => $roleBe->id,
        ]);
    }

    /**
     * Test GET /api/student/my-team when student has no team yet.
     */
    public function test_get_my_team_when_student_has_no_team(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $rolePm = Role::where('code', 'PM')->firstOrFail();
        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $rolePm->id,
            'final_role_id' => $rolePm->id,
        ]);

        $response = $this->getJson('/api/student/my-team');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'has_team' => false,
                    'final_role' => [
                        'id' => $rolePm->id,
                        'code' => 'PM',
                    ],
                    'team' => null,
                ],
            ]);
    }

    /**
     * Test GET /api/student/my-team when student is assigned to a team with project and supervisor.
     */
    public function test_get_my_team_when_student_has_assigned_team(): void
    {
        $supervisor = User::factory()->lecturer()->create([
            'name' => 'Dr. Budi Santoso, M.Kom.',
            'email' => 'budi.santoso@mail.ugm.ac.id',
        ]);

        $team = Team::create([
            'team_type' => 'internal',
            'is_locked' => false,
            'supervisor' => $supervisor->id,
        ]);

        $projectOwner = User::factory()->lecturer()->create([
            'name' => 'Prof. Haryanto',
            'email' => 'haryanto@mail.ugm.ac.id',
        ]);

        Project::create([
            'project_name' => 'Sistem Informasi PAD UGM',
            'project_owner' => $projectOwner->id,
            'project_associate' => 'Dinas Pendidikan DIY',
            'project_description' => 'Aplikasi manajemen alur projek PAD UGM',
            'project_team' => $team->id,
        ]);

        $rolePm = Role::where('code', 'PM')->firstOrFail();
        $roleFe = Role::where('code', 'FE')->firstOrFail();

        // Student 1 (current user)
        $student1 = User::factory()->student()->create([
            'name' => 'Ahmad Faiz',
            'email' => 'ahmad.faiz@mail.ugm.ac.id',
        ]);
        StudentProfile::create([
            'user_id' => $student1->id,
            'nim' => '21/473829/SV/19283',
            'team_id' => $team->id,
            'role_1_id' => $rolePm->id,
        ]);
        StudentRoleEvaluation::create([
            'user_id' => $student1->id,
            'recommended_role_id' => $rolePm->id,
            'final_role_id' => $rolePm->id,
        ]);

        // Student 2 (team member)
        $student2 = User::factory()->student()->create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti.nurhaliza@mail.ugm.ac.id',
        ]);
        StudentProfile::create([
            'user_id' => $student2->id,
            'nim' => '21/473830/SV/19284',
            'team_id' => $team->id,
            'role_1_id' => $roleFe->id,
        ]);
        StudentRoleEvaluation::create([
            'user_id' => $student2->id,
            'recommended_role_id' => $roleFe->id,
            'final_role_id' => $roleFe->id,
        ]);

        Sanctum::actingAs($student1);

        $response = $this->getJson('/api/student/my-team');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'has_team' => true,
                    'final_role' => [
                        'id' => $rolePm->id,
                        'code' => 'PM',
                    ],
                    'team' => [
                        'id' => $team->id,
                        'team_type' => 'internal',
                        'supervisor' => [
                            'id' => $supervisor->id,
                            'name' => 'Dr. Budi Santoso, M.Kom.',
                            'email' => 'budi.santoso@mail.ugm.ac.id',
                        ],
                        'project' => [
                            'name' => 'Sistem Informasi PAD UGM',
                            'associate' => 'Dinas Pendidikan DIY',
                        ],
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.team.members');
    }

    /**
     * Test routes accessible via /api/v1/ prefix.
     */
    public function test_v1_versioned_routes(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/student/profiling-config')->assertStatus(200);
        $this->getJson('/api/v1/student/my-team')->assertStatus(200);
    }
}
