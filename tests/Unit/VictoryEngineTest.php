<?php

namespace Tests\Unit;

use App\Services\GameEngine\VictoryEngine;
use PHPUnit\Framework\TestCase;

class VictoryEngineTest extends TestCase
{
    public function test_trickster_solo_victory_when_voted_out(): void
    {
        $room = [
            'round' => 1,
            'lastEliminatedPlayer' => ['playerId' => 'p_trickster', 'role' => 'trickster'],
            'lastEliminatedCause' => 'VOTE',
            'players' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
                ['playerId' => 'p_trickster', 'role' => 'trickster', 'faction' => 'neutral', 'isAlive' => false],
            ]
        ];

        $result = VictoryEngine::evaluateVictory($room);
        $this->assertTrue($result['isDecided']);
        $this->assertEquals('SOLO', $result['kind']);
        $this->assertEquals('trickster_voted_out', $result['ruleId']);
        $this->assertContains('p_trickster', $result['winnerIds']);
    }

    public function test_kingdom_victory_when_all_shadows_dead(): void
    {
        $room = [
            'round' => 1,
            'lastEliminatedPlayer' => null,
            'lastEliminatedCause' => null,
            'players' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p2', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p3', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => false],
            ]
        ];

        $result = VictoryEngine::evaluateVictory($room);
        $this->assertTrue($result['isDecided']);
        $this->assertEquals('KINGDOM', $result['faction']);
        $this->assertEquals('shadows_eliminated', $result['ruleId']);
    }

    public function test_shadow_victory_by_parity(): void
    {
        $room = [
            'round' => 1,
            'lastEliminatedPlayer' => null,
            'lastEliminatedCause' => null,
            'players' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ]
        ];

        $result = VictoryEngine::evaluateVictory($room);
        $this->assertTrue($result['isDecided']);
        $this->assertEquals('SHADOW', $result['faction']);
        $this->assertEquals('shadows_break_parity', $result['ruleId']);
    }

    public function test_shadow_victory_by_vacant_throne(): void
    {
        $room = [
            'round' => 1,
            'settings' => ['kingMustSurvive' => true],
            'vacantThrone' => true,
            'lastEliminatedPlayer' => null,
            'lastEliminatedCause' => null,
            'players' => [
                ['playerId' => 'p1', 'role' => 'citizen', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p2', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ]
        ];

        $result = VictoryEngine::evaluateVictory($room);
        $this->assertTrue($result['isDecided']);
        $this->assertEquals('SHADOW', $result['faction']);
        $this->assertEquals('king_must_survive', $result['ruleId']);
    }

    public function test_game_continues_when_undecided(): void
    {
        $room = [
            'round' => 1,
            'lastEliminatedPlayer' => null,
            'lastEliminatedCause' => null,
            'players' => [
                ['playerId' => 'p1', 'role' => 'king', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p2', 'role' => 'doctor', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p3', 'role' => 'investigator', 'faction' => 'kingdom', 'isAlive' => true],
                ['playerId' => 'p4', 'role' => 'assassin', 'faction' => 'shadow', 'isAlive' => true],
            ]
        ];

        $result = VictoryEngine::evaluateVictory($room);
        $this->assertFalse($result['isDecided']);
    }
}
