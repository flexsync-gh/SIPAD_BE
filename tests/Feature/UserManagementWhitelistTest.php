<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentRoleEvaluation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementWhitelistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. POST /api/admin/lecturers tests
     */
    public function test_initiate_lecturers_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/admin/lecturers', [
            'email' => 'dosen@ugm.ac.id',
        ]);

        $response->assertStatus(401);
    }

    public function test_initiate_lecturers_forbidden_for_non_admin_users(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->postJson('/api/admin/lecturers', [
            'email' => 'dosen2@ugm.ac.id',
        ]);

        $response->assertStatus(403);
    }

    public function test_master_admin_can_initiate_lecturers_single_and_bulk(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        // Single initiation
        $responseSingle = $this->postJson('/api/admin/lecturers', [
            'email' => 'dinar@ugm.ac.id',
            'name' => 'Ir. Dinar Nugroho Pratomo, S.Kom., M.IM., M.Cs.',
            'role' => 'admin',
        ]);

        $responseSingle->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'dinar@ugm.ac.id',
                    'user_role' => 'admin',
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'dinar@ugm.ac.id',
            'user_role' => 'admin',
        ]);

        // Bulk initiation
        $responseBulk = $this->postJson('/api/admin/lecturers', [
            'lecturers' => [
                ['email' => 'iwan@ugm.ac.id', 'name' => 'Ahmad Iwan Fadli, S.Kom., M.Eng.', 'role' => 'lecturer'],
                ['email' => 'dosen3@ugm.ac.id', 'name' => 'Dosen Tiga', 'role' => 'admin'],
            ],
        ]);

        $responseBulk->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'iwan@ugm.ac.id', 'user_role' => 'lecturer']);
        $this->assertDatabaseHas('users', ['email' => 'dosen3@ugm.ac.id', 'user_role' => 'admin']);
    }

    /**
     * 2. GET /api/users tests
     */
    public function test_get_users_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_get_users_forbidden_for_students(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_dosen_and_master_admin_can_get_users_with_filters(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        User::factory()->student()->create([
            'name' => 'Budi Susanto',
            'email' => 'budi@mail.ugm.ac.id',
            'status' => 'active',
        ]);

        User::factory()->assistant()->create([
            'name' => 'Siti Asprak',
            'email' => 'siti@mail.ugm.ac.id',
            'status' => 'archived',
        ]);

        // Filter role
        $responseRole = $this->getJson('/api/users?user_role=mahasiswa');
        $responseRole->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 1,
                ],
            ]);

        // Filter status
        $responseStatus = $this->getJson('/api/users?status=archived');
        $responseStatus->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 1,
                ],
            ]);

        // Search by name
        $responseSearch = $this->getJson('/api/users?search=Budi');
        $responseSearch->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 1,
                ],
            ]);
    }

    /**
     * 3. POST /api/users/whitelist tests
     */
    public function test_store_whitelist_typing_manual(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Single typing input
        $response = $this->postJson('/api/users/whitelist', [
            'email' => 'mahasiswa_krs@mail.ugm.ac.id',
            'name' => 'Mahasiswa Baru',
            'user_role' => 'mahasiswa',
            'nim' => '24/533333/SV/25000',
            'is_bypass' => false,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'email' => 'mahasiswa_krs@mail.ugm.ac.id',
                    'user_role' => 'student',
                    'student_profile' => [
                        'nim' => '24/533333/SV/25000',
                        'is_bypass' => false,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mahasiswa_krs@mail.ugm.ac.id',
            'user_role' => 'student',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'nim' => '24/533333/SV/25000',
            'is_bypass' => 0,
        ]);
    }

    /**
     * 4. POST /api/users/whitelist/import tests (Maatwebsite/Laravel-Excel)
     */
    public function test_import_krs_whitelist_csv(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $csvContent = "email,nim,name\n"
            ."krs1@mail.ugm.ac.id,23/500001/SV/10001,KRS Student One\n"
            ."krs2@mail.ugm.ac.id,23/500002/SV/10002,KRS Student Two\n"
            ."krs3@mail.ugm.ac.id,23/500003/SV/10003,KRS Student Three\n";

        $file = UploadedFile::fake()->createWithContent('daftar_krs.csv', $csvContent);

        $response = $this->postJson('/api/users/whitelist/import', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'total_imported' => 3,
            ]);

        $this->assertDatabaseHas('users', ['email' => 'krs1@mail.ugm.ac.id', 'user_role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'krs2@mail.ugm.ac.id', 'user_role' => 'student']);
        $this->assertDatabaseHas('users', ['email' => 'krs3@mail.ugm.ac.id', 'user_role' => 'student']);

        $this->assertDatabaseHas('student_profiles', ['nim' => '23/500001/SV/10001', 'is_bypass' => 0]);
        $this->assertDatabaseHas('student_profiles', ['nim' => '23/500002/SV/10002', 'is_bypass' => 0]);
        $this->assertDatabaseHas('student_profiles', ['nim' => '23/500003/SV/10003', 'is_bypass' => 0]);
    }

    /**
     * 5. POST /api/teams/exclusive tests
     */
    public function test_register_exclusive_team_gemastik_lidm(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $student1 = User::factory()->student()->create(['email' => 'gemastik1@mail.ugm.ac.id']);
        $student2 = User::factory()->student()->create(['email' => 'gemastik2@mail.ugm.ac.id']);

        $response = $this->postJson('/api/teams/exclusive', [
            'team_name' => 'Tim GEMASTIK Software UGM 1',
            'team_type' => 'external',
            'members' => [
                ['user_id' => $student1->id, 'nim' => '22/499991/SV/20001'],
                ['user_id' => $student2->id, 'nim' => '22/499992/SV/20002'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'team_type' => 'external',
                    'is_locked' => true,
                ],
            ]);

        $teamId = $response->json('data.id');

        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'team_type' => 'external',
            'is_locked' => 1,
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student1->id,
            'team_id' => $teamId,
            'is_bypass' => 1,
            'nim' => '22/499991/SV/20001',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student2->id,
            'team_id' => $teamId,
            'is_bypass' => 1,
            'nim' => '22/499992/SV/20002',
        ]);

        $this->assertDatabaseHas('projects', [
            'project_name' => 'Tim GEMASTIK Software UGM 1',
            'project_team' => $teamId,
        ]);
    }

    /**
     * 6. DELETE /api/users/{id} tests
     */
    public function test_delete_user_from_whitelist_succeeds_when_not_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '23/999999/SV/11111',
            'is_bypass' => false,
        ]);

        $response = $this->deleteJson("/api/users/{$student->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Email pengguna berhasil dihapus dari daftar whitelist.',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    public function test_delete_user_from_whitelist_rejected_when_role_is_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'nim' => '23/888888/SV/22222',
            'is_bypass' => false,
        ]);

        // Lock the role in evaluation
        StudentRoleEvaluation::create([
            'user_id' => $student->id,
            'is_locked' => true,
        ]);

        $response = $this->deleteJson("/api/users/{$student->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak. Pengguna tidak dapat dihapus dari whitelist karena peran (role) sudah dikunci.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_delete_user_from_whitelist_rejected_when_team_is_locked(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $team = Team::create([
            'team_type' => 'internal',
            'is_locked' => true,
        ]);

        $student = User::factory()->student()->create();
        StudentProfile::create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'nim' => '23/777777/SV/33333',
            'is_bypass' => false,
        ]);

        $response = $this->deleteJson("/api/users/{$student->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak. Pengguna tidak dapat dihapus dari whitelist karena peran (role) sudah dikunci.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }
}
