<?php

namespace Tests\Feature;

use App\Models\MatchLog;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\Analytics\RoleAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_calculates_role_matrix_with_match_history(): void
    {
        // Create 2 matches with shadow victory
        MatchLog::create([
            'room_code' => 'GAME1',
            'winning_faction' => 'SHADOW',
            'duration_seconds' => 180,
            'players_json' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => false],
                ['playerId' => 'p2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ],
        ]);

        MatchLog::create([
            'room_code' => 'GAME2',
            'winning_faction' => 'SHADOW',
            'duration_seconds' => 150,
            'players_json' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => false],
                ['playerId' => 'p2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ],
        ]);

        $matrix = RoleAnalyticsService::getRoleMatrix();

        $this->assertArrayHasKey('king', $matrix);
        $this->assertArrayHasKey('assassin', $matrix);

        // Assassin won 2/2 -> 100% win rate
        $this->assertEquals(2, $matrix['assassin']['appearances']);
        $this->assertEquals(2, $matrix['assassin']['wins']);
        $this->assertEquals(100.0, $matrix['assassin']['win_rate']);

        // King won 0/2 -> 0% win rate
        $this->assertEquals(2, $matrix['king']['appearances']);
        $this->assertEquals(0, $matrix['king']['wins']);
        $this->assertEquals(0.0, $matrix['king']['win_rate']);
    }

    public function test_computes_economy_metrics(): void
    {
        PlayerProfile::create(['player_id' => 'p_1', 'nickname' => 'تاجر', 'coins' => 1200]);
        PlayerProfile::create(['player_id' => 'p_2', 'nickname' => 'مواطن', 'coins' => 300]);

        $economy = RoleAnalyticsService::getEconomyMetrics();

        $this->assertEquals(1500, $economy['total_coins']);
        $this->assertEquals(2, $economy['total_players']);
        $this->assertEquals(750, $economy['avg_coins']);
        $this->assertEquals(1200, $economy['max_coins']);
        $this->assertCount(2, $economy['top_wealthy']);
    }

    public function test_admin_can_view_analytics_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/analytics');

        $response->assertStatus(200);
        $response->assertSee('تحليلات توازن الأدوار واقتصاد اللعبة');
        $response->assertSee('مصفوفة توازن الأدوار');
        $response->assertSee('إجمالي العملات المتداولة');
    }
}
