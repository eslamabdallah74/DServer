<?php

namespace Tests\Unit;

use App\Models\SuspiciousActivity;
use App\Services\Moderation\AntiCheatEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntiCheatEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_opposing_faction_players_sharing_same_ip(): void
    {
        $room = [
            'roomCode' => 'ABCD',
            'players' => [
                [
                    'playerId' => 'p1',
                    'nickname' => 'الملك_علي',
                    'ip' => '192.168.1.100',
                    'role' => 'king',
                    'faction' => 'kingdom',
                    'isBot' => false,
                ],
                [
                    'playerId' => 'p2',
                    'nickname' => 'السفاح_عمر',
                    'ip' => '192.168.1.100',
                    'role' => 'assassin',
                    'faction' => 'shadow',
                    'isBot' => false,
                ],
                [
                    'playerId' => 'p3',
                    'nickname' => 'طبيب_خالد',
                    'ip' => '10.0.0.1',
                    'role' => 'doctor',
                    'faction' => 'kingdom',
                    'isBot' => false,
                ],
            ]
        ];

        $suspicious = AntiCheatEngine::detectTeaming($room, true);

        $this->assertCount(1, $suspicious);
        $this->assertEquals('p1', $suspicious[0]['playerA']);
        $this->assertEquals('p2', $suspicious[0]['playerB']);
        $this->assertEquals('SAME_IP_OPPOSING_TEAMS', $suspicious[0]['reason']);
        $this->assertEquals('critical', $suspicious[0]['severity']);

        $this->assertDatabaseHas('suspicious_activities', [
            'room_code' => 'ABCD',
            'player_a_id' => 'p1',
            'player_b_id' => 'p2',
            'reason' => 'SAME_IP_OPPOSING_TEAMS',
        ]);
    }

    public function test_ignores_bots_in_ip_matching(): void
    {
        $room = [
            'roomCode' => 'BOTS',
            'players' => [
                [
                    'playerId' => 'p1',
                    'ip' => '192.168.1.50',
                    'role' => 'king',
                    'faction' => 'kingdom',
                    'isBot' => false,
                ],
                [
                    'playerId' => 'bot_1',
                    'ip' => '192.168.1.50',
                    'role' => 'assassin',
                    'faction' => 'shadow',
                    'isBot' => true,
                ],
            ]
        ];

        $suspicious = AntiCheatEngine::detectTeaming($room, false);
        $this->assertEmpty($suspicious);
    }

    public function test_flags_toxic_chat_message(): void
    {
        $result = AntiCheatEngine::checkChatToxicity('أنت كلب يا غشاش', 'p_test', 'ROOM1', true);

        $this->assertTrue($result['toxic']);
        $this->assertEquals('CHAT_TOXICITY', $result['reason']);

        $this->assertDatabaseHas('suspicious_activities', [
            'room_code' => 'ROOM1',
            'player_a_id' => 'p_test',
            'reason' => 'CHAT_TOXICITY',
        ]);
    }

    public function test_flags_blatant_role_leak_message(): void
    {
        $result = AntiCheatEngine::checkChatToxicity('أنا القاتل لا تصوتون ضدي', 'p_leak', 'ROOM2', true);

        $this->assertTrue($result['toxic']);
        $this->assertEquals('ROLE_LEAK', $result['reason']);
    }

    public function test_permits_clean_chat_message(): void
    {
        $result = AntiCheatEngine::checkChatToxicity('أنا أشك في اللاعب رقم 3 لأنه هادئ', 'p_clean', 'ROOM3', false);

        $this->assertFalse($result['toxic']);
        $this->assertNull($result['reason']);
    }
}

