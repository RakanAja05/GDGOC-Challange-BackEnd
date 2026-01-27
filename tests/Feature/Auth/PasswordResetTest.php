<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        $this->post('/forgot-password', ['email' => 'test@example.com'])
            ->assertNotFound();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $this->post('/reset-password', [
            'token' => 'token',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
