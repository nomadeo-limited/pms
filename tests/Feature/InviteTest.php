<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use Tests\TestCase;

class InviteTest extends TestCase
{
    private function invite(array $overrides = []): array
    {
        $org = $this->createOrganizer('invite-org');
        $admin = $this->createOrganizerAdmin($org);

        $payload = array_merge([
            'name' => 'Jane Surf',
            'email' => 'jane@surf.camp',
            'role' => 'organizer_staff',
        ], $overrides);

        $response = $this->postJson('/api/v1/staff/invite', $payload, $this->orgHeaders($admin, $org));

        return [$org, $admin, $response];
    }

    public function test_invite_creates_inactive_user_with_token(): void
    {
        [, , $response] = $this->invite();

        $response->assertStatus(201)
            ->assertJsonPath('email', 'jane@surf.camp')
            ->assertJsonStructure(['id', 'name', 'email', 'invite_token', 'invite_expires_at']);

        $user = User::where('email', 'jane@surf.camp')->first();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->invite_token);
    }

    public function test_invite_token_is_stored_as_sha256_hash(): void
    {
        [, , $response] = $this->invite();

        $plainToken = $response->json('invite_token');
        $user = User::where('email', 'jane@surf.camp')->first();

        $this->assertSame(hash('sha256', $plainToken), $user->invite_token);
    }

    public function test_inactive_user_cannot_log_in_before_accepting_invite(): void
    {
        $this->invite();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@surf.camp',
            'password' => 'anything',
        ])->assertStatus(401);
    }

    public function test_accept_invite_activates_account_and_returns_jwt(): void
    {
        [, , $inviteResponse] = $this->invite();

        $response = $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $inviteResponse->json('invite_token'),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $user = User::where('email', 'jane@surf.camp')->first();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->invite_token);
        $this->assertNull($user->invite_token_expires_at);
    }

    public function test_accepted_user_can_log_in_with_new_password(): void
    {
        [, , $inviteResponse] = $this->invite();

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $inviteResponse->json('invite_token'),
            'password' => 'newsecret99',
            'password_confirmation' => 'newsecret99',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@surf.camp',
            'password' => 'newsecret99',
        ])->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_invalid_token_returns_422(): void
    {
        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => 'totally-fake-token',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonPath('message', 'Invalid or expired invite token.');
    }

    public function test_expired_token_returns_422(): void
    {
        [, , $inviteResponse] = $this->invite();

        User::where('email', 'jane@surf.camp')
            ->update(['invite_token_expires_at' => now()->subHour()]);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $inviteResponse->json('invite_token'),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonPath('message', 'Invalid or expired invite token.');
    }

    public function test_invite_token_cannot_be_reused(): void
    {
        [, , $inviteResponse] = $this->invite();
        $plainToken = $inviteResponse->json('invite_token');

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $plainToken,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $plainToken,
            'password' => 'different99',
            'password_confirmation' => 'different99',
        ])->assertStatus(422);
    }

    public function test_password_confirmation_must_match(): void
    {
        [, , $inviteResponse] = $this->invite();

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $inviteResponse->json('invite_token'),
            'password' => 'secret123',
            'password_confirmation' => 'doesnotmatch',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_must_be_at_least_8_characters(): void
    {
        [, , $inviteResponse] = $this->invite();

        $this->postJson('/api/v1/auth/accept-invite', [
            'token' => $inviteResponse->json('invite_token'),
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
