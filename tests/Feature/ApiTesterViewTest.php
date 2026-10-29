<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTesterViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test tester UI loads successfully at root URL.
     */
    public function test_tester_ui_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSeeText('PAD API Tester & Playground');
        $response->assertSeeText('Stakeholders & Whitelist');
    }

    /**
     * Test web google callback handles incoming authorization code.
     */
    public function test_web_google_callback_renders_with_code(): void
    {
        $response = $this->get('/auth/google/callback?code=mock-google-code');

        $response->assertStatus(200);
        $response->assertSee('mock-google-code');
    }

    /**
     * Test dev users endpoint returns users list.
     */
    public function test_dev_users_endpoint_returns_json(): void
    {
        User::factory()->create([
            'email' => 'dev@mail.ugm.ac.id',
            'user_role' => UserRole::Admin,
        ]);

        $response = $this->getJson('/dev/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'email', 'user_role', 'status'],
                ],
            ]);
    }

    /**
     * Test dev generate token endpoint issues valid sanctum token.
     */
    public function test_dev_generate_token_issues_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test.token@mail.ugm.ac.id',
            'user_role' => UserRole::Lecturer,
            'status' => 'active',
        ]);

        $response = $this->postJson('/dev/generate-token', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure(['token', 'user']);

        $token = $response->json('token');

        // Verify the token works against auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }
}
