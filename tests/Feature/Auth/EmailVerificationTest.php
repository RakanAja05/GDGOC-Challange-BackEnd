<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationOtp;
use App\Models\EmailVerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        EmailVerificationStatus::create([
            'user_id' => $user->id,
            'status' => 'unverified',
            'verified_at' => null,
        ]);

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'code_encrypted' => null,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/email/verification/verify', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $status = EmailVerificationStatus::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($status);
        $this->assertSame('verified', $status->status);
    }

    public function test_email_is_not_verified_with_invalid_code(): void
    {
        $user = User::factory()->unverified()->create();

        EmailVerificationStatus::create([
            'user_id' => $user->id,
            'status' => 'unverified',
            'verified_at' => null,
        ]);

        EmailVerificationOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'code_encrypted' => null,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used_at' => null,
        ]);

        $this->postJson('/api/email/verification/verify', [
            'email' => $user->email,
            'code' => '000000',
        ])->assertStatus(422);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
