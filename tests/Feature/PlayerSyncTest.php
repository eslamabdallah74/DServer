<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'ownedRoles' => ['doctor', 'investigator'],
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

    public function test_coins_increment_and_decrement(): void
    {
        $headers = ['x-jwt-secret' => 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'];

        // Initial sync
        $this->withHeaders($headers)->postJson('/api/player', [
            'playerId' => 'player_coin_test',
            'coins' => 100,
        ]);

        // Increment by 50 -> 150
        $incRes = $this->withHeaders($headers)->postJson('/api/coins/increment', [
            'playerId' => 'player_coin_test',
            'amount' => 50,
        ]);
        $incRes->assertStatus(200);
        $this->assertEquals(150, $incRes->json('coins'));

        // Decrement by 30 -> 120
        $decRes = $this->withHeaders($headers)->postJson('/api/coins/decrement', [
            'playerId' => 'player_coin_test',
            'amount' => 30,
        ]);
        $decRes->assertStatus(200);
        $this->assertEquals(120, $decRes->json('coins'));
    }

    public function test_announcement_endpoint(): void
    {
        Cache::put('latest_system_broadcast', ['message' => 'تحديث تجريبي'], 60);

        $res = $this->getJson('/api/announcement');
        $res->assertStatus(200);
        $this->assertEquals('تحديث تجريبي', $res->json('announcement.message'));
    }

    public function test_log_match_and_issues(): void
    {
        $headers = ['x-jwt-secret' => 'deceit_offline_super_secret_jwt_key_2026_998877665544332211'];

        // Log match
        $matchRes = $this->withHeaders($headers)->postJson('/api/matches/log', [
            'roomCode' => 'TEST',
            'winnerTeam' => 'KINGDOM',
            'playerCount' => 6,
            'durationSeconds' => 180,
        ]);
        $matchRes->assertStatus(200);
        $this->assertTrue($matchRes->json('success'));

        // Log issue
        $issueRes = $this->withHeaders($headers)->postJson('/api/issues', [
            'severity' => 'error',
            'message' => 'Connection timeout on voting phase',
            'page' => 'VotingScene',
        ]);
        $issueRes->assertStatus(200);
        $this->assertTrue($issueRes->json('success'));

        // Get issues
        $getIssuesRes = $this->withHeaders($headers)->getJson('/api/issues');
        $getIssuesRes->assertStatus(200);
        $this->assertNotEmpty($getIssuesRes->json('data'));
    }
}
