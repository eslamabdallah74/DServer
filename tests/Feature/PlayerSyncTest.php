<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'OK',
                     'server' => 'Deceit Server (Laravel 11)',
                 ]);
    }

    public function test_player_sync_with_jwt_secret_header(): void
    {
        $payload = [
            'playerId' => 'device_laravel_test_99',
            'nickname' => 'LaravelPlayer',
            'coins' => 750,
            'ownedRoles' => ['doctor', 'detective'],
            'stats' => [
                'wins' => 5,
                'losses' => 2,
                'matches' => 7,
            ]
        ];

        $response = $this->withHeaders([
            'x-jwt-secret' => 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'
        ])->postJson('/api/sync/player', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'reconciled' => true,
                     'profile' => [
                         'playerId' => 'device_laravel_test_99',
                         'nickname' => 'LaravelPlayer',
                         'coins' => 750,
                     ]
                 ]);
    }

    public function test_player_sync_reconciles_coins_with_max_value(): void
    {
        // First sync: 500 coins
        $this->withHeaders([
            'x-jwt-secret' => 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'
        ])->postJson('/api/sync/player', [
            'playerId' => 'device_reconcile_1',
            'nickname' => 'P1',
            'coins' => 500,
        ]);

        // Second sync with 300 coins should preserve 500 (max)
        $response = $this->withHeaders([
            'x-jwt-secret' => 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'
        ])->postJson('/api/sync/player', [
            'playerId' => 'device_reconcile_1',
            'nickname' => 'P1',
            'coins' => 300,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'profile' => [
                         'coins' => 500,
                     ]
                 ]);
    }
}
