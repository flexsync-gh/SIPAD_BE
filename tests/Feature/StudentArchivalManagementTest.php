<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentArchivalManagementTest extends TestCase
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
        $this->getJson('/api/students/archivable')->assertStatus(401);
        $this->postJson('/api/students/archive', ['user_ids' => [1]])->assertStatus(401);
    }

    /**
     * Test non-lecturer roles (student, assistant, practitioner) cannot access archival management (403).
     */
    public function test_non_lecturer_roles_cannot_access_archival(): void
    {
        $student = User::factory()->student()->create();
        $assistant = User::factory()->assistant()->create();
        $practitioner = User::factory()->practitioner()->create();

        foreach ([$student, $assistant, $practitioner] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/students/archivable')->assertStatus(403);
            $this->postJson('/api/students/archive', ['user_ids' => [1]])->assertStatus(403);
        }
    }

    /**
     * Test GET /api/students/archivable retrieves only active students.
     */
    public function test_get_archivable_retrieves_only_active_students(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Active student
        $activeStudent = User::factory()->student()->create([
            'name' => 'Ahmad Faiz',
            'status' => 'active',
        ]);
        StudentProfile::create([
            'user_id' => $activeStudent->id,
            'nim' => '21/473829/SV/19283',
        ]);

        // Already archived student
        $archivedStudent = User::factory()->student()->create([
            'name' => 'Budi Santoso',
            'status' => 'archived',
        ]);
        StudentProfile::create([
            'user_id' => $archivedStudent->id,
            'nim' => '20/461234/SV/18123',
        ]);

        // Lecturer user (non-student)
        User::factory()->lecturer()->create(['status' => 'active']);

        $response = $this->getJson('/api/students/archivable');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 1,
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeStudent->id, $data[0]['user_id']);
        $this->assertEquals('21/473829/SV/19283', $data[0]['nim']);
        $this->assertEquals('2021', $data[0]['angkatan']);
    }

    /**
     * Test GET /api/students/archivable filtering by angkatan and search.
     */
    public function test_get_archivable_filtering_by_angkatan_and_search(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        // Angkatan 2021 (NIM 21/...)
        $student21 = User::factory()->student()->create(['name' => 'Citra Angkatan 21']);
        StudentProfile::create(['user_id' => $student21->id, 'nim' => '21/111111/SV/11111']);

        // Angkatan 2024 (NIM 24/...)
        $student24 = User::factory()->student()->create(['name' => 'Doni Angkatan 24']);
        StudentProfile::create(['user_id' => $student24->id, 'nim' => '24/222222/SV/22222']);

        // Filter angkatan = 2024
        $res24 = $this->getJson('/api/students/archivable?angkatan=2024');
        $res24->assertStatus(200);
        $this->assertCount(1, $res24->json('data'));
        $this->assertEquals($student24->id, $res24->json('data.0.user_id'));

        // Filter angkatan = 21 (2-digit)
        $res21 = $this->getJson('/api/students/archivable?angkatan=21');
        $res21->assertStatus(200);
        $this->assertCount(1, $res21->json('data'));
        $this->assertEquals($student21->id, $res21->json('data.0.user_id'));

        // Search by name
        $resSearch = $this->getJson('/api/students/archivable?search=Doni');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals($student24->id, $resSearch->json('data.0.user_id'));
    }

    /**
     * Test POST /api/students/archive updates status to archived.
     */
    public function test_post_archive_updates_status_in_transaction(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $student1 = User::factory()->student()->create(['status' => 'active']);
        $student2 = User::factory()->student()->create(['status' => 'active']);
        $student3 = User::factory()->student()->create(['status' => 'active']);

        $response = $this->postJson('/api/students/archive', [
            'user_ids' => [$student1->id, $student2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'archived_count' => 2,
                    'archived_user_ids' => [$student1->id, $student2->id],
                ],
            ]);

        // Verify status in database
        $this->assertEquals('archived', $student1->fresh()->status);
        $this->assertEquals('archived', $student2->fresh()->status);
        $this->assertEquals('active', $student3->fresh()->status);

        // Archived students no longer returned in archivable list
        $resList = $this->getJson('/api/students/archivable');
        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));
        $this->assertEquals($student3->id, $resList->json('data.0.user_id'));
    }

    /**
     * Test POST /api/students/archive validation for empty array.
     */
    public function test_post_archive_validation_rejects_empty(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->postJson('/api/students/archive', [
            'user_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);
    }

    /**
     * Test POST /api/students/unarchive restores archived students.
     */
    public function test_post_unarchive_restores_students(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $student = User::factory()->student()->create(['status' => 'archived']);

        $response = $this->postJson('/api/students/unarchive', [
            'user_ids' => [$student->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'restored_count' => 1,
                ],
            ]);

        $this->assertEquals('active', $student->fresh()->status);
    }

    /**
     * Test versioned routes (/api/v1/students/...).
     */
    public function test_v1_versioned_routes(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/v1/students/archivable')->assertStatus(200);
    }
}
