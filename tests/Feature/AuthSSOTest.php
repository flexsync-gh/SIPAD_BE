<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class AuthSSOTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting Google OAuth redirect URL.
     */
    public function test_can_get_google_redirect_url(): void
    {
        $redirectMock = Mockery::mock(RedirectResponse::class);
        $redirectMock->shouldReceive('getTargetUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?client_id=test');

        $providerMock = Mockery::mock(GoogleProvider::class);
        $providerMock->shouldReceive('stateless')->andReturnSelf();
        $providerMock->shouldReceive('redirect')->andReturn($redirectMock);

        Socialite::shouldReceive('driver')->with('google')->andReturn($providerMock);

        $response = $this->getJson('/api/auth/google/redirect');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'url' => 'https://accounts.google.com/o/oauth2/auth?client_id=test',
            ]);
    }

    /**
     * Test callback fails when neither code nor token is provided.
     */
    public function test_callback_fails_when_no_code_or_token_provided(): void
    {
        $response = $this->postJson('/api/auth/google/callback', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Authorization code atau access token dari Google wajib disertakan.',
            ]);
    }

    /**
     * Test callback rejects unverified Google email.
     */
    public function test_callback_rejects_unverified_email(): void
    {
        $this->mockGoogleUser([
            'id' => 'google-111',
            'email' => 'unverified@mail.ugm.ac.id',
            'email_verified' => false,
        ]);

        $response = $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code-123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak, email belum diverifikasi oleh Google.',
            ]);
    }

    /**
     * Test callback rejects non-mail.ugm.ac.id email domains.
     */
    public function test_callback_rejects_non_ugm_domain(): void
    {
        // 1. Regular gmail
        $this->mockGoogleUser([
            'id' => 'google-222',
            'email' => 'someone@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code-123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak, hanya email dengan domain @mail.ugm.ac.id yang diizinkan untuk login.',
            ]);

        // 2. Domain ugm.ac.id without mail. prefix
        $this->mockGoogleUser([
            'id' => 'google-223',
            'email' => 'someone@ugm.ac.id',
            'email_verified' => true,
        ]);

        $response2 = $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code-456',
        ]);

        $response2->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak, hanya email dengan domain @mail.ugm.ac.id yang diizinkan untuk login.',
            ]);
    }

    /**
     * Test callback rejects email not in active KRS whitelist.
     */
    public function test_callback_rejects_email_not_in_krs_whitelist(): void
    {
        $this->mockGoogleUser([
            'id' => 'google-333',
            'email' => 'notinkrs@mail.ugm.ac.id',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code-123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak, email tidak terdaftar pada KRS aktif.',
            ]);
    }

    /**
     * Test callback rejects user with archived status.
     */
    public function test_callback_rejects_archived_user(): void
    {
        User::factory()->archived()->create([
            'email' => 'archived@mail.ugm.ac.id',
        ]);

        $this->mockGoogleUser([
            'id' => 'google-444',
            'email' => 'archived@mail.ugm.ac.id',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/google/callback', [
            'code' => 'auth-code-123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak, email tidak terdaftar pada KRS aktif.',
            ]);
    }

    /**
     * Test successful login with code returns token and saves Google sub and avatar.
     */
    public function test_callback_success_with_valid_krs_user(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'mahasiswa1@mail.ugm.ac.id',
            'name' => 'Original Name',
            'google_id_sub' => null,
            'avatar_link' => null,
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $user->id,
            'nim' => '23/512345/SV/22001',
            'is_bypass' => true,
        ]);

        $this->mockGoogleUser([
            'id' => 'google-sub-999888',
            'email' => 'mahasiswa1@mail.ugm.ac.id',
            'name' => 'Mahasiswa UGM',
            'avatar' => 'https://lh3.googleusercontent.com/avatar.jpg',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/api/auth/google/callback', [
            'code' => 'valid-google-code',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'email',
                    'user_role',
                    'role',
                    'is_bypass',
                    'google_id_sub',
                    'avatar_link',
                    'student_profile',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'email' => 'mahasiswa1@mail.ugm.ac.id',
                    'google_id_sub' => 'google-sub-999888',
                    'avatar_link' => 'https://lh3.googleusercontent.com/avatar.jpg',
                    'is_bypass' => true,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id_sub' => 'google-sub-999888',
            'avatar_link' => 'https://lh3.googleusercontent.com/avatar.jpg',
        ]);
    }

    /**
     * Test successful login with access_token parameter.
     */
    public function test_callback_success_with_access_token(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'mahasiswa2@mail.ugm.ac.id',
            'status' => 'active',
        ]);

        $this->mockGoogleUser([
            'id' => 'google-sub-777',
            'email' => 'mahasiswa2@mail.ugm.ac.id',
            'email_verified' => true,
        ], useToken: true);

        $response = $this->postJson('/api/auth/google/callback', [
            'access_token' => 'valid-access-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'email' => 'mahasiswa2@mail.ugm.ac.id',
                    'google_id_sub' => 'google-sub-777',
                ],
            ]);
    }

    /**
     * Test /api/auth/me returns current authenticated user with profile, is_bypass, and role.
     */
    public function test_auth_me_returns_profile_for_authenticated_user(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student.me@mail.ugm.ac.id',
            'user_role' => UserRole::Student,
        ]);

        StudentProfile::create([
            'user_id' => $user->id,
            'nim' => '22/499999/SV/21111',
            'is_bypass' => false,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'email' => 'student.me@mail.ugm.ac.id',
                    'user_role' => 'student',
                    'role' => 'student',
                    'is_bypass' => false,
                    'student_profile' => [
                        'nim' => '22/499999/SV/21111',
                        'is_bypass' => false,
                    ],
                ],
            ]);
    }

    /**
     * Test /api/auth/me returns 401 when unauthenticated.
     */
    public function test_auth_me_unauthenticated(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test /api/auth/logout deletes current Sanctum token.
     */
    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'logout.user@mail.ugm.ac.id',
        ]);

        $token = $user->createToken('token-to-delete')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Berhasil logout, token telah dihapus.',
            ]);

        $this->assertDatabaseEmpty('personal_access_tokens');

        $this->app['auth']->forgetGuards();

        // Subsequent call with revoked token should fail with 401
        $responseSubsequent = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $responseSubsequent->assertStatus(401);
    }

    /**
     * Helper to mock Google Socialite user and provider.
     */
    protected function mockGoogleUser(array $data, bool $useToken = false): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($data['id'] ?? 'google-default-id');
        $socialiteUser->shouldReceive('getEmail')->andReturn($data['email'] ?? 'default@mail.ugm.ac.id');
        $socialiteUser->shouldReceive('getName')->andReturn($data['name'] ?? 'Default User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn($data['avatar'] ?? 'https://example.com/avatar.jpg');
        $socialiteUser->shouldReceive('getRaw')->andReturn([
            'email_verified' => $data['email_verified'] ?? true,
        ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();

        if ($useToken) {
            $provider->shouldReceive('userFromToken')->andReturn($socialiteUser);
        } else {
            $provider->shouldReceive('user')->andReturn($socialiteUser);
        }

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
