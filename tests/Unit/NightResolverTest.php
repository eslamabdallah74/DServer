<?php

namespace Tests\Unit;

use App\Services\GameEngine\NightResolver;
use PHPUnit\Framework\TestCase;

class NightResolverTest extends TestCase
{
    public function test_assassin_kills_unprotected_target(): void
    {
        $room = [
            'players' => [
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_assassin' => [
                    'actorPlayerId' => 'p_assassin',
                    'roleId' => 'assassin',
                    'targetPlayerId' => 'p_victim',
                ]
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        $killedIds = array_column($outcome['killed'], 'playerId');
        $this->assertContains('p_victim', $killedIds);
        $this->assertFalse($room['players'][1]['isAlive']);
    }

    public function test_doctor_saves_target_from_assassin(): void
    {
        $room = [
            'players' => [
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_doctor', 'nickname' => 'الطبيب', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_assassin' => [
                    'actorPlayerId' => 'p_assassin',
                    'roleId' => 'assassin',
                    'targetPlayerId' => 'p_victim',
                ],
                'p_doctor' => [
                    'actorPlayerId' => 'p_doctor',
                    'roleId' => 'doctor',
                    'targetPlayerId' => 'p_victim',
                ]
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        $killedIds = array_column($outcome['killed'], 'playerId');
        $savedIds = array_column($outcome['saved'], 'playerId');

        $this->assertNotContains('p_victim', $killedIds);
        $this->assertTrue($room['players'][2]['isAlive']);
        $this->assertContains('p_victim', $savedIds);
    }

    public function test_saboteur_blocks_doctor_allowing_kill(): void
    {
        $room = [
            'players' => [
                ['playerId' => 'p_saboteur', 'nickname' => 'المخرب', 'role' => 'saboteur', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_assassin', 'nickname' => 'القاتل', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_doctor', 'nickname' => 'الطبيب', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
                ['playerId' => 'p_victim', 'nickname' => 'الضحية', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true, 'statuses' => []],
            ],
            'nightActions' => [
                'p_saboteur' => [
                    'actorPlayerId' => 'p_saboteur',
                    'roleId' => 'saboteur',
                    'targetPlayerId' => 'p_doctor',
                ],
                'p_doctor' => [
                    'actorPlayerId' => 'p_doctor',
                    'roleId' => 'doctor',
                    'targetPlayerId' => 'p_victim',
                ],
                'p_assassin' => [
                    'actorPlayerId' => 'p_assassin',
                    'roleId' => 'assassin',
                    'targetPlayerId' => 'p_victim',
                ],
            ]
        ];

        $outcome = NightResolver::resolveNight($room);

        $killedIds = array_column($outcome['killed'], 'playerId');
        $this->assertContains('p_victim', $killedIds);
        $this->assertFalse($room['players'][3]['isAlive']);
    }
}

