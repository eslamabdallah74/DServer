<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GameEngine\GameConfigService;
use App\Services\GameEngine\RoomManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTuningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GameConfigService::clearCache();
    }

    public function test_guest_cannot_access_tuning_page(): void
    {
        $response = $this->get('/admin/tuning');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_tuning_page(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/tuning');
        $response->assertStatus(200);
        $response->assertSee('الضبط المباشر لقواعد اللعبة');
        $response->assertSee('dayTimerSeconds');
    }

    public function test_admin_can_update_tuning_settings(): void
    {
        $admin = User::factory()->create();

        $payload = [
            'dayTimerSeconds'        => 90,
            'nightTimerSeconds'      => 45,
            'votingTimerSeconds'     => 35,
            'roleRevealTimerSeconds' => 6,
            'winCoinReward'          => 100,
            'lossCoinReward'         => 25,
            'tieRule'                => 'RANDOM_ELIMINATION',
            'kingMustSurvive'        => 1,
            'disabledRoles'          => ['slasher', 'wizard'],
        ];

        $response = $this->actingAs($admin)->post('/admin/tuning', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(90, GameConfigService::get('day_timer_seconds'));
        $this->assertEquals(45, GameConfigService::get('night_timer_seconds'));
        $this->assertEquals(['slasher', 'wizard'], GameConfigService::getDisabledRoles());
        $this->assertEquals(100, GameConfigService::get('win_coin_reward'));

        // Verify newly created rooms automatically adopt the updated timers
        $created = RoomManager::createRoom('مضيف_تجريبي');
        $room = $created['room'];
        $this->assertEquals(90, $room['settings']['dayTimerSeconds']);
        $this->assertEquals(45, $room['settings']['nightTimerSeconds']);
        $this->assertEquals(35, $room['settings']['votingTimerSeconds']);
    }
}
