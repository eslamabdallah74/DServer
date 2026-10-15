<?php

namespace Tests\Unit;

use App\Models\GameConfiguration;
use App\Services\GameEngine\GameConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GameConfigService::clearCache();
    }

    public function test_returns_default_configuration_when_no_db_entry(): void
    {
        $dayTimer = GameConfigService::get('day_timer_seconds');
        $this->assertEquals(120, $dayTimer);

        $timers = GameConfigService::getTimers();
        $this->assertEquals(120, $timers['dayTimerSeconds']);
        $this->assertEquals(60, $timers['nightTimerSeconds']);
        $this->assertEquals(60, $timers['votingTimerSeconds']);
        $this->assertEquals(8, $timers['roleRevealTimerSeconds']);
    }

    public function test_persists_and_retrieves_live_configuration(): void
    {
        GameConfigService::set('day_timer_seconds', 45, 'Custom short day');

        $this->assertEquals(45, GameConfigService::get('day_timer_seconds'));
        $this->assertDatabaseHas('game_configurations', [
            'key' => 'day_timer_seconds',
            'value' => '45',
        ]);

        $timers = GameConfigService::getTimers();
        $this->assertEquals(45, $timers['dayTimerSeconds']);
    }

    public function test_handles_disabled_roles_array(): void
    {
        $this->assertFalse(GameConfigService::isRoleDisabled('slasher'));

        GameConfigService::set('disabled_roles', ['slasher', 'forger']);

        $this->assertTrue(GameConfigService::isRoleDisabled('slasher'));
        $this->assertTrue(GameConfigService::isRoleDisabled('forger'));
        $this->assertFalse(GameConfigService::isRoleDisabled('doctor'));
        $this->assertEquals(['slasher', 'forger'], GameConfigService::getDisabledRoles());
    }
}

