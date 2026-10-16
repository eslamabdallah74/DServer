<?php

namespace Tests\Feature;

use App\Services\GameEngine\RoomManager;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();
    }

    public function test_room_lifecycle_create_join_ready_and_start(): void
    {
        // 1. Create room
        $createRes = $this->postJson('/api/rooms/create', [
            'nickname' => 'القائد',
            'settings' => ['maxPlayers' => 8]
        ]);

        $createRes->assertStatus(201);
        $createData = $createRes->json();
        $this->assertTrue($createData['success']);
        $this->assertNotEmpty($createData['roomCode']);
        $this->assertEquals(4, strlen($createData['roomCode']));

        $roomCode = $createData['roomCode'];
        $hostId = $createData['player']['playerId'];

        // 2. Second player joins
        $joinRes = $this->postJson('/api/rooms/join', [
            'roomCode' => $roomCode,
            'nickname' => 'المحارب'
        ]);

        $joinRes->assertStatus(200);
        $joinData = $joinRes->json();
        $this->assertTrue($joinData['success']);
        $player2Id = $joinData['player']['playerId'];

        // 3. Host tries to start before player 2 is ready -> should fail
        $startFailRes = $this->postJson("/api/rooms/{$roomCode}/start", [
            'playerId' => $hostId
        ]);

        $startFailRes->assertStatus(400);
        $this->assertEquals('NOT_ALL_PLAYERS_READY', $startFailRes->json('error'));

        // 4. Player 2 marks ready
        $readyRes = $this->postJson("/api/rooms/{$roomCode}/ready", [
            'playerId' => $player2Id,
            'isReady' => true
        ]);

        $readyRes->assertStatus(200);
        $this->assertTrue($readyRes->json('success'));

        // 5. Host starts game successfully
        $startSuccessRes = $this->postJson("/api/rooms/{$roomCode}/start", [
            'playerId' => $hostId
        ]);

        $startSuccessRes->assertStatus(200);
        $this->assertTrue($startSuccessRes->json('success'));
        $this->assertEquals('ROLE_REVEAL', $startSuccessRes->json('room.phase'));
    }

    public function test_room_details_and_sync(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'سالم']);
        $createRes->assertStatus(201);
        $roomCode = $createRes->json('roomCode');
        $playerId = $createRes->json('player.playerId');

        // Show room details
        $showRes = $this->getJson("/api/rooms/{$roomCode}");
        $showRes->assertStatus(200);
        $this->assertTrue($showRes->json('success'));
        $this->assertEquals($roomCode, $showRes->json('room.roomCode'));

        // Sync endpoint
        $syncRes = $this->getJson("/api/rooms/{$roomCode}/sync?playerId={$playerId}");
        $syncRes->assertStatus(200);
        $this->assertTrue($syncRes->json('success'));
    }

    public function test_add_and_remove_bot(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'المضيف']);
        $createRes->assertStatus(201);
        $roomCode = $createRes->json('roomCode');
        $hostId = $createRes->json('player.playerId');

        // Add bot
        $addBotRes = $this->postJson("/api/rooms/{$roomCode}/add-bot", [
            'playerId' => $hostId
        ]);
        $addBotRes->assertStatus(200);
        $this->assertTrue($addBotRes->json('success'));
        $botId = $addBotRes->json('bot.playerId');

        // Remove bot
        $removeBotRes = $this->postJson("/api/rooms/{$roomCode}/remove-bot", [
            'playerId' => $hostId,
            'botPlayerId' => $botId
        ]);
        $removeBotRes->assertStatus(200);
        $this->assertTrue($removeBotRes->json('success'));
    }

    public function test_chat_in_room(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'أحمد']);
        $createRes->assertStatus(201);
        $roomCode = $createRes->json('roomCode');
        $playerId = $createRes->json('player.playerId');

        $chatRes = $this->postJson("/api/rooms/{$roomCode}/chat", [
            'playerId' => $playerId,
            'text' => 'مرحباً بالجميع في المباراة!'
        ]);

        $chatRes->assertStatus(200);
        $this->assertTrue($chatRes->json('success'));
        $this->assertEquals('مرحباً بالجميع في المباراة!', $chatRes->json('chatMessage.text'));
    }

    public function test_leave_and_kick(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'المضيف']);
        $createRes->assertStatus(201);
        $roomCode = $createRes->json('roomCode');
        $hostId = $createRes->json('player.playerId');

        $joinRes = $this->postJson('/api/rooms/join', [
            'roomCode' => $roomCode,
            'nickname' => 'الزائر'
        ]);
        $guestId = $joinRes->json('player.playerId');

        // Host kicks guest
        $kickRes = $this->postJson("/api/rooms/{$roomCode}/kick-player", [
            'playerId' => $hostId,
            'targetPlayerId' => $guestId
        ]);
        $kickRes->assertStatus(200);
        $this->assertTrue($kickRes->json('success'));

        // Host leaves -> room destroyed
        $leaveRes = $this->postJson("/api/rooms/{$roomCode}/leave", [
            'playerId' => $hostId
        ]);
        $leaveRes->assertStatus(200);
        $this->assertTrue($leaveRes->json('success'));

        // Room should no longer exist
        $this->assertNull(RoomManager::getRoom($roomCode));
    }

    public function test_create_room_is_rate_limited_after_threshold(): void
    {
        // 20 requests within rate limit succeed
        for ($i = 0; $i < 20; $i++) {
            $res = $this->postJson('/api/rooms/create', ['nickname' => "لاعب_{$i}"]);
            $this->assertContains($res->status(), [201, 200]);
        }

        // 21st request exceeds rate limit (429 Too Many Requests)
        $rateLimitedRes = $this->postJson('/api/rooms/create', ['nickname' => 'لاعب_زائد']);
        $rateLimitedRes->assertStatus(429);
    }

    public function test_reconnect_to_room_with_valid_and_invalid_token(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'المسترجع']);
        $createRes->assertStatus(201);
        $roomCode = $createRes->json('roomCode');
        $token = $createRes->json('reconnectToken');
        $playerId = $createRes->json('player.playerId');

        // 1. Reconnect with invalid token -> fails 400
        $failRes = $this->postJson("/api/rooms/{$roomCode}/reconnect", [
            'reconnectToken' => 'fake_invalid_token_1234567890'
        ]);
        $failRes->assertStatus(400);
        $this->assertEquals('INVALID_TOKEN', $failRes->json('error'));

        // 2. Reconnect with valid token -> succeeds 200 and restores session
        $successRes = $this->postJson("/api/rooms/{$roomCode}/reconnect", [
            'reconnectToken' => $token
        ]);
        $successRes->assertStatus(200);
        $this->assertTrue($successRes->json('success'));
        $this->assertEquals($playerId, $successRes->json('playerId'));
        $this->assertEquals($roomCode, $successRes->json('roomCode'));
        $this->assertTrue($successRes->json('player.isConnected'));
    }

    public function test_quick_play_creates_new_room_if_none_open_and_joins_existing_open_room(): void
    {
        $res1 = $this->postJson('/api/rooms/quick-play', [
            'nickname' => 'لاعب_سريع_1',
        ]);
        $res1->assertStatus(200);
        $this->assertTrue($res1->json('success'));
        $this->assertTrue($res1->json('isNewRoom'));
        $roomCode = $res1->json('roomCode');
        $this->assertNotEmpty($roomCode);

        $res2 = $this->postJson('/api/rooms/quick-play', [
            'nickname' => 'لاعب_سريع_2',
        ]);
        $res2->assertStatus(200);
        $this->assertTrue($res2->json('success'));
        $this->assertFalse($res2->json('isNewRoom'));
        $this->assertEquals($roomCode, $res2->json('roomCode'));
    }

    public function test_ghost_chat_allows_dead_players_and_blocks_living_players(): void
    {
        $createRes = $this->postJson('/api/rooms/create', ['nickname' => 'المضيف']);
        $roomCode = $createRes->json('roomCode');
        $hostId = $createRes->json('playerId');

        $joinRes = $this->postJson('/api/rooms/join', ['roomCode' => $roomCode, 'nickname' => 'الميت']);
        $deadPlayerId = $joinRes->json('playerId');

        // Mark room in DAY phase and make player dead
        $room = RoomManager::getRoom($roomCode);
        $room['phase'] = 'DAY_VOTING';
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $deadPlayerId) {
                $p['isAlive'] = false;
            }
        }
        RoomManager::saveRoom($room);

        // 1. Dead player trying to chat in PUBLIC channel fails
        $failRes = $this->postJson("/api/rooms/{$roomCode}/chat", [
            'playerId' => $deadPlayerId,
            'text' => 'أنا ميت وأتحدث!',
            'channel' => 'PUBLIC',
        ]);
        $failRes->assertStatus(400);
        $this->assertEquals('DEAD_PLAYERS_CANNOT_CHAT', $failRes->json('error'));

        // 2. Dead player chatting in GHOST channel succeeds
        $ghostRes = $this->postJson("/api/rooms/{$roomCode}/chat", [
            'playerId' => $deadPlayerId,
            'text' => 'مرحباً من عالم الأشباح!',
            'channel' => 'GHOST',
        ]);
        $ghostRes->assertStatus(200);
        $this->assertTrue($ghostRes->json('success'));
        $this->assertEquals('GHOST', $ghostRes->json('message.channel'));

        // 3. Living player trying to send to GHOST channel is blocked
        $livingGhostFail = $this->postJson("/api/rooms/{$roomCode}/chat", [
            'playerId' => $hostId,
            'text' => 'أنا حي وأريد التجسس',
            'channel' => 'GHOST',
        ]);
        $livingGhostFail->assertStatus(400);
        $this->assertEquals('ALIVE_PLAYERS_CANNOT_USE_GHOST_CHAT', $livingGhostFail->json('error'));
    }
}


