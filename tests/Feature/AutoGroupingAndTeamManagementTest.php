<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoGroupingAndTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    /**
     * Helper to create a regular student with a locked final role.
     */
    protected function createStudentWithRole(string $roleCode, string $nim): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $student = User::factory()->student()->create();

        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => $nim,
            'is_bypass' => false,
            'role_1_id' => $role->id,
        ]);

        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'recommended_role_id' => $role->id,
            'recommended_score' => 85.0,
            'final_role_id' => $role->id,
            'is_locked' => true,
        ]);

        return $student;
    }

    /**
     * Test unauthenticated access returns 401.
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/teams')->assertStatus(401);
        $this->postJson('/api/teams/auto-group')->assertStatus(401);
        $this->postJson('/api/teams/reset-grouping')->assertStatus(401);
        $this->patchJson('/api/teams/1/supervisor', ['supervisor_id' => 1])->assertStatus(401);
        $this->postJson('/api/teams/1/project', ['project_name' => 'Project A'])->assertStatus(401);
    }

    /**
     * Test students cannot access team management endpoints (403).
     */
    public function test_student_cannot_access_team_management(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/teams')->assertStatus(403);
        $this->postJson('/api/teams/auto-group')->assertStatus(403);
        $this->postJson('/api/teams/reset-grouping')->assertStatus(403);
        $this->patchJson('/api/teams/1/supervisor', ['supervisor_id' => 1])->assertStatus(403);
        $this->postJson('/api/teams/1/project', ['project_name' => 'Project A'])->assertStatus(403);
    }

    /**
     * Test practitioner access permissions (can read teams, cannot group/reset/assign supervisor/manage project).
     */
    public function test_practitioner_access_restrictions(): void
    {
        $practitioner = User::factory()->practitioner()->create();
        Sanctum::actingAs($practitioner);

        // Can read
        $this->getJson('/api/teams')->assertStatus(200);

        // Cannot write/modify
        $this->postJson('/api/teams/auto-group')->assertStatus(403);
        $this->postJson('/api/teams/reset-grouping')->assertStatus(403);
        $this->patchJson('/api/teams/1/supervisor', ['supervisor_id' => 1])->assertStatus(403);
        $this->postJson('/api/teams/1/project', ['project_name' => 'Project A'])->assertStatus(403);
    }

    /**
     * Test auto-group engine forms balanced teams with PM, UIUX, FE, BE.
     */
    public function test_auto_group_creates_balanced_teams(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Create 2 students for each of the 4 roles = 8 students total (should form 2 teams)
        $this->createStudentWithRole('PM', '21/100001/SV/00001');
        $this->createStudentWithRole('PM', '21/100002/SV/00002');
        $this->createStudentWithRole('UIUX', '21/200001/SV/00001');
        $this->createStudentWithRole('UIUX', '21/200002/SV/00002');
        $this->createStudentWithRole('FE', '21/300001/SV/00001');
        $this->createStudentWithRole('FE', '21/300002/SV/00002');
        $this->createStudentWithRole('BE', '21/400001/SV/00001');
        $this->createStudentWithRole('BE', '21/400002/SV/00002');

        // Also create a bypass student (GEMASTIK) who must NOT be auto-grouped
        $bypassStudent = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $bypassStudent->id,
            'nim' => '21/999999/SV/99999',
            'is_bypass' => true,
        ]);
        StudentRoleEvaluation::create([
            'user_id' => $bypassStudent->id,
            'recommended_role_id' => 1,
            'final_role_id' => 1,
            'is_locked' => true,
        ]);

        $response = $this->postJson('/api/teams/auto-group');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'teams_created_count' => 2,
                    'students_grouped_count' => 8,
                    'remaining_students_count' => 0,
                ],
            ]);

        // Verify in database: 2 internal teams created
        $this->assertEquals(2, Team::where('team_type', 'internal')->count());

        // Verify each team has exactly 4 members
        $teams = Team::where('team_type', 'internal')->with('members.evaluation.finalRole')->get();
        foreach ($teams as $team) {
            $this->assertCount(4, $team->members);
            $codes = $team->members->map(fn ($m) => $m->evaluation->finalRole->code)->sort()->values()->toArray();
            $this->assertEquals(['BE', 'FE', 'PM', 'UIUX'], $codes);
        }

        // Verify bypass student has no team_id assigned from auto-group
        $this->assertNull($bypassStudent->fresh()->studentProfile->team_id);
    }

    /**
     * Test auto-group fails when a role has 0 candidates.
     */
    public function test_auto_group_fails_when_role_missing(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Only PM, UIUX, FE (missing BE)
        $this->createStudentWithRole('PM', '21/100001/SV/00001');
        $this->createStudentWithRole('UIUX', '21/200001/SV/00001');
        $this->createStudentWithRole('FE', '21/300001/SV/00001');

        $response = $this->postJson('/api/teams/auto-group');

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    /**
     * Test auto-group rejects if regular teams are already locked.
     */
    public function test_auto_group_rejects_when_regular_teams_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        Team::create([
            'team_type' => 'internal',
            'is_locked' => true,
        ]);

        $response = $this->postJson('/api/teams/auto-group');

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Pembagian tim reguler telah dikunci dan tidak dapat diacak ulang.',
            ]);
    }

    /**
     * Test reset grouping unassigns regular students and removes internal teams.
     */
    public function test_reset_grouping_clears_regular_teams(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Create an internal team with students
        $team = Team::create(['team_type' => 'internal', 'is_locked' => false]);
        $student = User::factory()->student()->create();
        $profile = StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '21/123456/SV/12345',
            'team_id' => $team->id,
            'is_bypass' => false,
        ]);

        // Create an external/exclusive team that must NOT be touched
        $exclusiveTeam = Team::create(['team_type' => 'external', 'is_locked' => true]);
        $bypassStudent = User::factory()->student()->create();
        $bypassProfile = StudentProfile::create([
            'user_id' => $bypassStudent->id,
            'nim' => '21/999999/SV/99999',
            'team_id' => $exclusiveTeam->id,
            'is_bypass' => true,
        ]);

        $response = $this->postJson('/api/teams/reset-grouping');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'reset_teams_count' => 1,
                ],
            ]);

        // Regular student is detached
        $this->assertNull($profile->fresh()->team_id);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);

        // Exclusive team remains intact
        $this->assertEquals($exclusiveTeam->id, $bypassProfile->fresh()->team_id);
        $this->assertDatabaseHas('teams', ['id' => $exclusiveTeam->id]);
    }

    /**
     * Test GET /api/teams filters by supervisor for Assistant.
     */
    public function test_get_teams_filtered_for_assistant(): void
    {
        $asprak1 = User::factory()->assistant()->create(['name' => 'Asprak 1']);
        $asprak2 = User::factory()->assistant()->create(['name' => 'Asprak 2']);
        $lecturer = User::factory()->lecturer()->create();

        $team1 = Team::create(['team_type' => 'internal', 'supervisor' => $asprak1->id]);
        $team2 = Team::create(['team_type' => 'internal', 'supervisor' => $asprak2->id]);

        // Lecturer sees all teams
        Sanctum::actingAs($lecturer);
        $resLecturer = $this->getJson('/api/teams');
        $resLecturer->assertStatus(200);
        $this->assertCount(2, $resLecturer->json('data'));

        // Asprak 1 sees only team 1
        Sanctum::actingAs($asprak1);
        $resAsprak = $this->getJson('/api/teams');
        $resAsprak->assertStatus(200);
        $this->assertCount(1, $resAsprak->json('data'));
        $this->assertEquals($team1->id, $resAsprak->json('data.0.id'));
    }

    /**
     * Test PATCH /api/teams/{teamId}/supervisor assigns assistant.
     */
    public function test_assign_supervisor_to_team(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $assistant = User::factory()->assistant()->create(['name' => 'Asprak Budi']);
        $team = Team::create(['team_type' => 'internal']);

        Sanctum::actingAs($lecturer);

        $response = $this->patchJson("/api/teams/{$team->id}/supervisor", [
            'supervisor_id' => $assistant->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'team_id' => $team->id,
                    'supervisor' => [
                        'id' => $assistant->id,
                        'name' => 'Asprak Budi',
                    ],
                ],
            ]);

        $this->assertEquals($assistant->id, $team->fresh()->supervisor);
    }

    /**
     * Test POST /api/teams/{teamId}/project saves project metadata.
     */
    public function test_store_or_update_project_metadata(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $team = Team::create(['team_type' => 'internal']);

        Sanctum::actingAs($lecturer);

        $response = $this->postJson("/api/teams/{$team->id}/project", [
            'project_name' => 'Sistem Rekomendasi PAD',
            'project_associate' => 'Dinas Kominfo',
            'project_description' => 'Aplikasi rekomendasi tim dan role',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'project_team' => $team->id,
                    'project_name' => 'Sistem Rekomendasi PAD',
                    'project_associate' => 'Dinas Kominfo',
                    'project_description' => 'Aplikasi rekomendasi tim dan role',
                ],
            ]);

        $this->assertDatabaseHas('projects', [
            'project_team' => $team->id,
            'project_name' => 'Sistem Rekomendasi PAD',
        ]);
    }

    /**
     * Test assistant cannot manage project of a team they do not supervise.
     */
    public function test_assistant_cannot_manage_project_of_unsupervised_team(): void
    {
        $asprak1 = User::factory()->assistant()->create();
        $asprak2 = User::factory()->assistant()->create();
        $team = Team::create(['team_type' => 'internal', 'supervisor' => $asprak1->id]);

        Sanctum::actingAs($asprak2);

        $response = $this->postJson("/api/teams/{$team->id}/project", [
            'project_name' => 'Unauthorized Project',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda hanya dapat mengelola proyek kelompok yang Anda bimbing.',
            ]);
    }

    /**
     * Test versioned routes (/api/v1/teams/...).
     */
    public function test_v1_versioned_routes(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/v1/teams')->assertStatus(200);
        $this->postJson('/api/v1/teams/reset-grouping')->assertStatus(200);
    }
}
