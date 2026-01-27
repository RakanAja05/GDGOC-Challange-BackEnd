<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_users_can_login_and_get_token(): void
    {
        $user = User::factory()->create();

        EmailVerificationStatus::create([
            'user_id' => $user->id,
            'status' => 'verified',
            'verified_at' => $user->email_verified_at,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'email'],
            ]);
    }

    public function test_unverified_users_cannot_login(): void
    {
        $user = User::factory()->unverified()->create();

        EmailVerificationStatus::create([
            'user_id' => $user->id,
            'status' => 'unverified',
            'verified_at' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(409);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        EmailVerificationStatus::create([
            'user_id' => $user->id,
            'status' => 'verified',
            'verified_at' => $user->email_verified_at,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertOk();
        $this->assertCount(0, $user->fresh()->tokens);
    }
}
