<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingsAndRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    /**
     * 1. GET /api/settings tests
     */
    public function test_get_settings_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertStatus(401);
    }

    public function test_all_logged_in_users_can_get_settings(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'is_role_2_required',
                    'is_role_2_required_label',
                    'registration_timeline',
                    'registration_terms',
                    'registration_status',
                    'academic_year',
                    'all_settings',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_role_2_required' => false,
                    'is_role_2_required_label' => 'opsional',
                ],
            ]);
    }

    /**
     * 2. PUT /api/settings tests
     */
    public function test_update_settings_unauthenticated_returns_401(): void
    {
        $response = $this->putJson('/api/settings', [
            'is_role_2_required' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_settings_forbidden_for_students(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->putJson('/api/settings', [
            'is_role_2_required' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_dosen_can_update_toggle_is_role_2_required_and_timeline(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $payload = [
            'is_role_2_required' => 'wajib',
            'registration_timeline' => 'Pendaftaran Diperpanjang s.d. 20 November 2026',
            'registration_terms' => 'Wajib memilih 2 role yang berbeda.',
        ];

        $response = $this->putJson('/api/settings', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_role_2_required' => true,
                    'is_role_2_required_label' => 'wajib',
                    'registration_timeline' => 'Pendaftaran Diperpanjang s.d. 20 November 2026',
                    'registration_terms' => 'Wajib memilih 2 role yang berbeda.',
                ],
            ]);

        $this->assertEquals('true', AppSetting::getValue('is_role_2_required'));
        $this->assertEquals('true', AppSetting::getValue('is_role_2_mandatory'));
        $this->assertEquals('Pendaftaran Diperpanjang s.d. 20 November 2026', AppSetting::getValue('registration_timeline'));
    }

    public function test_dosen_can_toggle_role_2_back_to_opsional(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $response = $this->putJson('/api/settings', [
            'is_role_2_required' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_role_2_required' => false,
                    'is_role_2_required_label' => 'opsional',
                ],
            ]);

        $this->assertEquals('false', AppSetting::getValue('is_role_2_required'));
    }

    /**
     * 3. GET /api/roles tests
     */
    public function test_get_roles_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/roles');

        $response->assertStatus(401);
    }

    public function test_all_logged_in_users_can_get_roles_with_skills(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'skills' => [
                            '*' => [
                                'id',
                                'role_id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);

        $roleCodes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('PM', $roleCodes);
        $this->assertContains('UIUX', $roleCodes);
        $this->assertContains('FE', $roleCodes);
        $this->assertContains('BE', $roleCodes);
    }

    /**
     * 4. POST /api/skills & PUT /api/skills/{id} tests
     */
    public function test_create_skill_forbidden_for_students(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/skills', [
            'role_code' => 'BE',
            'name' => 'Docker & Containerization',
        ]);

        $response->assertStatus(403);
    }

    public function test_dosen_can_create_new_skill_item(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $beRole = Role::where('code', 'BE')->firstOrFail();

        $response = $this->postJson('/api/skills', [
            'role_id' => $beRole->id,
            'name' => 'Docker & Containerization',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'role_id' => $beRole->id,
                    'name' => 'Docker & Containerization',
                ],
            ]);

        $this->assertDatabaseHas('skills', [
            'role_id' => $beRole->id,
            'name' => 'Docker & Containerization',
        ]);
    }

    public function test_dosen_can_update_existing_skill_item(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $skill = Skill::firstOrFail();

        $response = $this->putJson("/api/skills/{$skill->id}", [
            'name' => 'Updated Advanced Skill Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $skill->id,
                    'name' => 'Updated Advanced Skill Name',
                ],
            ]);

        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'name' => 'Updated Advanced Skill Name',
        ]);
    }

    public function test_dosen_can_delete_skill_item(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        Sanctum::actingAs($lecturer);

        $skill = Skill::firstOrFail();

        $response = $this->deleteJson("/api/skills/{$skill->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Item skillset berhasil dihapus.',
            ]);

        $this->assertDatabaseMissing('skills', [
            'id' => $skill->id,
        ]);
    }
}
