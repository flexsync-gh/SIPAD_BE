<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalComplaintBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
        Storage::fake('local');
    }

    /**
     * Test unauthenticated access returns 401.
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/complaints')->assertStatus(401);
        $this->getJson('/api/complaints/1')->assertStatus(401);
        $this->postJson('/api/complaints', [])->assertStatus(401);
        $this->patchJson('/api/complaints/1/review')->assertStatus(401);
    }

    /**
     * Test POST /api/complaints validation failures.
     */
    public function test_store_complaint_validation_failures(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        // Missing required fields
        $resEmpty = $this->postJson('/api/complaints', []);
        $resEmpty->assertStatus(422)
            ->assertJsonValidationErrors(['reported_id', 'description', 'evidence_file']);

        // Self-reporting rejection
        $file = UploadedFile::fake()->create('evidence.pdf', 500, 'application/pdf');
        $resSelf = $this->postJson('/api/complaints', [
            'reported_id' => $student->id,
            'description' => 'Saya ingin melaporkan diri sendiri.',
            'evidence_file' => $file,
        ]);
        $resSelf->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'Anda tidak dapat melaporkan diri Anda sendiri.']);

        // Reporter not in a team
        $otherStudent = User::factory()->student()->create();
        $resNoTeam = $this->postJson('/api/complaints', [
            'reported_id' => $otherStudent->id,
            'description' => 'Teman saya tidak mengerjakan tugas sama sekali.',
            'evidence_file' => $file,
        ]);
        $resNoTeam->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'Anda belum terdaftar dalam kelompok sehingga tidak dapat mengajukan keluhan tim.']);
    }

    /**
     * Test POST /api/complaints rejects when reported student is not in the same team.
     */
    public function test_store_complaint_rejects_different_team_reported(): void
    {
        $team1 = Team::create(['team_type' => 'internal']);
        $team2 = Team::create(['team_type' => 'internal']);

        $student1 = User::factory()->student()->create();
        StudentProfile::create(['user_id' => $student1->id, 'team_id' => $team1->id]);

        $student2 = User::factory()->student()->create();
        StudentProfile::create(['user_id' => $student2->id, 'team_id' => $team2->id]);

        Sanctum::actingAs($student1);

        $file = UploadedFile::fake()->create('bukti.png', 300, 'image/png');

        $response = $this->postJson('/api/complaints', [
            'reported_id' => $student2->id,
            'description' => 'Mahasiswa tim lain tidak mau berkoordinasi.',
            'evidence_file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Mahasiswa yang dilaporkan harus berada dalam satu kelompok yang sama dengan Anda.',
            ]);
    }

    /**
     * Test POST /api/complaints successfully stores complaint and file in private storage.
     */
    public function test_store_complaint_successfully_stores_in_private_storage(): void
    {
        $team = Team::create(['team_type' => 'internal']);

        $reporter = User::factory()->student()->create(['name' => 'Budi Pelapor']);
        StudentProfile::create(['user_id' => $reporter->id, 'team_id' => $team->id, 'nim' => '21/111111/SV/11111']);

        $reported = User::factory()->student()->create(['name' => 'Doni Terlapor']);
        StudentProfile::create(['user_id' => $reported->id, 'team_id' => $team->id, 'nim' => '21/222222/SV/22222']);

        Sanctum::actingAs($reporter);

        $file = UploadedFile::fake()->create('bukti_chat.pdf', 1024, 'application/pdf');

        $response = $this->postJson('/api/complaints', [
            'reported_id' => $reported->id,
            'description' => 'Doni tidak pernah merespons chat kelompok dan mangkir saat sprint.',
            'evidence_file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Tiket pengaduan anggota tim berhasil dikirim.',
                'data' => [
                    'team_id' => $team->id,
                    'reporter' => ['id' => $reporter->id, 'name' => 'Budi Pelapor'],
                    'reported' => ['id' => $reported->id, 'name' => 'Doni Terlapor'],
                    'description' => 'Doni tidak pernah merespons chat kelompok dan mangkir saat sprint.',
                    'is_reviewed' => false,
                ],
            ]);

        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        $this->assertEquals($reporter->id, $complaint->reporter_id);
        $this->assertEquals($reported->id, $complaint->reported_id);
        $this->assertEquals($team->id, $complaint->team_id);

        // Verify file stored in fake local storage
        Storage::disk('local')->assertExists($complaint->evidence_file_path);
    }

    /**
     * Test GET /api/complaints role-based visibility.
     */
    public function test_get_complaints_role_based_filtering(): void
    {
        $asprak1 = User::factory()->assistant()->create(['name' => 'Asprak 1']);
        $asprak2 = User::factory()->assistant()->create(['name' => 'Asprak 2']);
        $lecturer = User::factory()->lecturer()->create();
        $practitioner = User::factory()->practitioner()->create();

        $team1 = Team::create(['team_type' => 'internal', 'supervisor' => $asprak1->id]);
        $team2 = Team::create(['team_type' => 'internal', 'supervisor' => $asprak2->id]);

        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $studentC = User::factory()->student()->create();
        $studentD = User::factory()->student()->create();

        // Complaint 1 in Team 1 (reported by studentA)
        $complaint1 = Complaint::create([
            'reporter_id' => $studentA->id,
            'reported_id' => $studentB->id,
            'team_id' => $team1->id,
            'description' => 'Masalah di tim 1',
            'evidence_file_path' => 'complaints/test1.pdf',
        ]);

        // Complaint 2 in Team 2 (reported by studentC)
        $complaint2 = Complaint::create([
            'reporter_id' => $studentC->id,
            'reported_id' => $studentD->id,
            'team_id' => $team2->id,
            'description' => 'Masalah di tim 2',
            'evidence_file_path' => 'complaints/test2.pdf',
        ]);

        // 1. Lecturer sees both complaints
        Sanctum::actingAs($lecturer);
        $resLecturer = $this->getJson('/api/complaints');
        $resLecturer->assertStatus(200);
        $this->assertCount(2, $resLecturer->json('data'));

        // 2. Practitioner sees both complaints
        Sanctum::actingAs($practitioner);
        $resPractitioner = $this->getJson('/api/complaints');
        $resPractitioner->assertStatus(200);
        $this->assertCount(2, $resPractitioner->json('data'));

        // 3. Asprak 1 only sees Complaint 1 (from team 1)
        Sanctum::actingAs($asprak1);
        $resAsprak1 = $this->getJson('/api/complaints');
        $resAsprak1->assertStatus(200);
        $this->assertCount(1, $resAsprak1->json('data'));
        $this->assertEquals($complaint1->id, $resAsprak1->json('data.0.id'));

        // 4. Student A only sees Complaint 1 (their own filed complaint)
        Sanctum::actingAs($studentA);
        $resStudentA = $this->getJson('/api/complaints');
        $resStudentA->assertStatus(200);
        $this->assertCount(1, $resStudentA->json('data'));
        $this->assertEquals($complaint1->id, $resStudentA->json('data.0.id'));

        // 5. Student B (reported user) does NOT see complaints against him via index
        Sanctum::actingAs($studentB);
        $resStudentB = $this->getJson('/api/complaints');
        $resStudentB->assertStatus(200);
        $this->assertCount(0, $resStudentB->json('data'));
    }

    /**
     * Test GET /api/complaints/{id} detail and confidential access control.
     */
    public function test_get_complaint_detail_confidentiality(): void
    {
        $asprak1 = User::factory()->assistant()->create();
        $asprak2 = User::factory()->assistant()->create();
        $lecturer = User::factory()->lecturer()->create();

        $team = Team::create(['team_type' => 'internal', 'supervisor' => $asprak1->id]);

        $reporter = User::factory()->student()->create();
        $reported = User::factory()->student()->create();
        $unrelatedStudent = User::factory()->student()->create();

        Storage::disk('local')->put('complaints/secret.pdf', 'Contoh isi berkas bukti rahasia');

        $complaint = Complaint::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'team_id' => $team->id,
            'description' => 'Keluhan rahasia tim',
            'evidence_file_path' => 'complaints/secret.pdf',
        ]);

        // 1. Reporter can view detail
        Sanctum::actingAs($reporter);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(200);

        // 2. Unrelated student is blocked (403)
        Sanctum::actingAs($unrelatedStudent);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(403);

        // 3. Reported student is blocked from reading the ticket (403)
        Sanctum::actingAs($reported);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(403);

        // 4. Assigned Asprak can view detail
        Sanctum::actingAs($asprak1);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(200);

        // 5. Unassigned Asprak is blocked (403)
        Sanctum::actingAs($asprak2);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(403);

        // 6. Lecturer can view detail
        Sanctum::actingAs($lecturer);
        $this->getJson("/api/complaints/{$complaint->id}")->assertStatus(200);
    }

    /**
     * Test download / stream evidence file from private storage.
     */
    public function test_download_evidence_file(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $team = Team::create(['team_type' => 'internal']);

        $reporter = User::factory()->student()->create();
        $reported = User::factory()->student()->create();

        Storage::disk('local')->put('complaints/sample.pdf', 'PDF CONTENT HERE');

        $complaint = Complaint::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'team_id' => $team->id,
            'description' => 'Bukti PDF',
            'evidence_file_path' => 'complaints/sample.pdf',
        ]);

        Sanctum::actingAs($lecturer);

        $response = $this->get("/api/complaints/{$complaint->id}/evidence");
        $response->assertStatus(200);
    }

    /**
     * Test PATCH /api/complaints/{id}/review marks ticket as reviewed.
     */
    public function test_review_complaint_ticket(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $asprak = User::factory()->assistant()->create();
        $student = User::factory()->student()->create();
        $reportedStudent = User::factory()->student()->create();

        $team = Team::create(['team_type' => 'internal', 'supervisor' => $asprak->id]);

        $complaint = Complaint::create([
            'reporter_id' => $student->id,
            'reported_id' => $reportedStudent->id,
            'team_id' => $team->id,
            'description' => 'Keluhan untuk ditinjau',
            'evidence_file_path' => 'complaints/review.pdf',
        ]);

        // Student cannot review (403)
        Sanctum::actingAs($student);
        $this->patchJson("/api/complaints/{$complaint->id}/review")->assertStatus(403);

        // Asprak can review their supervised team's complaint
        Sanctum::actingAs($asprak);
        $resAsprak = $this->patchJson("/api/complaints/{$complaint->id}/review");
        $resAsprak->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $complaint->id,
                    'is_reviewed' => true,
                    'reviewed_by' => [
                        'id' => $asprak->id,
                    ],
                ],
            ]);

        $this->assertNotNull($complaint->fresh()->reviewed_at);
        $this->assertEquals($asprak->id, $complaint->fresh()->reviewed_by);

        // Lecturer can also review
        Sanctum::actingAs($lecturer);
        $resLecturer = $this->patchJson("/api/complaints/{$complaint->id}/review");
        $resLecturer->assertStatus(200);
        $this->assertEquals($lecturer->id, $complaint->fresh()->reviewed_by);
    }

    /**
     * Test versioned routes (/api/v1/complaints/...).
     */
    public function test_v1_versioned_routes(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $this->getJson('/api/v1/complaints')->assertStatus(200);
    }
}
