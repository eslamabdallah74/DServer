<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GameEngine\RoomManager;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSpectatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_access_spectator_page(): void
    {
        $response = $this->get('/admin/spectator/ABCD');
        $response->assertRedirect('/login');
    }

    public function test_redirects_if_room_does_not_exist(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/spectator/NONEXISTENT');
        $response->assertRedirect('/admin/rooms');
        $response->assertSessionHas('error');
    }

    public function test_admin_can_view_live_spectator_god_mode(): void
    {
        $created = RoomManager::createRoom('مستضيف_المباراة');
        $code = $created['room']['roomCode'];
        $hostId = $created['hostPlayer']['playerId'];

        // Add 5 bots to reach 6 players
        for ($i = 0; $i < 5; $i++) {
            RoomManager::addBot($code);
        }

        // Start game so roles are assigned
        RoomManager::startGame($code, $hostId);

        $response = $this->actingAs($this->admin)->get("/admin/spectator/{$code}");
        $response->assertStatus(200);
        $response->assertSee('شاشة المراقبة الحية الشاملة');
        $response->assertSee($code);
        $response->assertSee('مستضيف_المباراة');
        $response->assertSee('الدور السري (مكشوف للمشرف)');
    }

    public function test_spectator_json_snapshot_reveals_unredacted_state(): void
    {
        $created = RoomManager::createRoom('مستضيف_السري');
        $code = $created['room']['roomCode'];
        $hostId = $created['hostPlayer']['playerId'];

        for ($i = 0; $i < 5; $i++) {
            RoomManager::addBot($code);
        }

        RoomManager::startGame($code, $hostId);

        $response = $this->actingAs($this->admin)->getJson("/admin/spectator/{$code}/snapshot");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'snapshot' => [
                'roomCode' => $code,
            ],
        ]);

        $json = $response->json();
        $players = $json['snapshot']['players'];
        $this->assertCount(6, $players);

        // Verify secret role and faction are visible in God-Mode
        foreach ($players as $p) {
            $this->assertNotNull($p['role']);
            $this->assertNotNull($p['faction']);
            $this->assertArrayHasKey('incomingVotes', $p);
        }
    }

    public function test_night_action_and_broadcast_updates_spectator_channel(): void
    {
        $created = RoomManager::createRoom('مستضيف_الليل');
        $code = $created['room']['roomCode'];
        $hostId = $created['hostPlayer']['playerId'];

        for ($i = 0; $i < 5; $i++) {
            RoomManager::addBot($code);
        }

        RoomManager::startGame($code, $hostId);

        // Fast forward to NIGHT
        $room = RoomManager::getRoom($code);
        RoomManager::advanceMatchLoop($room);

        // Verify Pusher broadcast was triggered to admin-spectator channel
        PusherService::assertBroadcast(
            "admin-spectator-{$code}",
            's_spectator_update'
        );
    }
}
