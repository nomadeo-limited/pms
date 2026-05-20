<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_returns_jwt_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123'), 'is_active' => true]);
        $user->assignRole('super_admin');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct'), 'is_active' => true]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createSuperAdmin();
        $headers = $this->superAdminHeaders($user);

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
