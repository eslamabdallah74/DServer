<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin@offline.local',
            'password' => 'deceit74',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'admin' => [
                         'email' => 'admin@offline.local'
                     ]
                 ]);
    }

    public function test_admin_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin@offline.local',
            'password' => 'wrongpassword123',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'INVALID_CREDENTIALS'
                 ]);
    }
}
