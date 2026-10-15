<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\SuspiciousActivity;
use App\Models\User;
use App\Services\GameEngine\ChatEngine;
use App\Services\GameEngine\RoomManager;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_access_moderation_page(): void
    {
        $response = $this->get('/admin/moderation');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_moderation_dashboard(): void
    {
        SuspiciousActivity::create([
            'room_code' => 'TEST',
            'player_a_id' => 'p_a',
            'player_b_id' => 'p_b',
            'reason' => 'SAME_IP_OPPOSING_TEAMS',
            'severity' => 'critical',
            'ip_address' => '1.2.3.4',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/moderation');
        $response->assertStatus(200);
        $response->assertSee('منظومة مكافحة التحالف والغش');
        $response->assertSee('TEST');
        $response->assertSee('تحالف IP');
    }

    public function test_admin_can_toggle_shadow_ban(): void
    {
        $profile = PlayerProfile::create([
            'player_id' => 'p_shadow',
            'nickname' => 'شبح_الديسيت',
            'coins' => 100,
            'is_shadow_banned' => false,
        ]);

        $this->assertFalse($profile->isShadowBanned());

        $res = $this->actingAs($this->admin)->post("/admin/moderation/players/{$profile->player_id}/shadow-ban");
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->isShadowBanned());
        $this->assertTrue($profile->is_shadow_banned);
    }

    public function test_admin_can_mute_and_unmute_player(): void
    {
        $profile = PlayerProfile::create([
            'player_id' => 'p_toxic',
            'nickname' => 'لاعب_مخالف',
            'coins' => 50,
        ]);

        // Mute for 1440 minutes (24 hours)
        $res = $this->actingAs($this->admin)->post("/admin/moderation/players/{$profile->player_id}/mute", [
            'duration' => 1440,
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->isMuted());

        // Test ChatEngine blocks muted player
        $room = [
            'roomCode' => 'ABCD',
            'phase' => 'LOBBY',
            'chatHistory' => [],
        ];
        $player = [
            'playerId' => $profile->player_id,
            'nickname' => $profile->nickname,
            'isAlive' => true,
        ];

        $chatResult = ChatEngine::processMessage($room, $player, 'مرحباً بالجميع');
        $this->assertArrayHasKey('error', $chatResult);
        $this->assertEquals('PLAYER_MUTED', $chatResult['error']);

        // Unmute
        $res2 = $this->actingAs($this->admin)->post("/admin/moderation/players/{$profile->player_id}/mute", [
            'duration' => 0,
        ]);
        $res2->assertRedirect();

        $profile->refresh();
        $this->assertFalse($profile->isMuted());
    }

    public function test_admin_can_dismiss_suspicious_activity(): void
    {
        $act = SuspiciousActivity::create([
            'room_code' => 'TEST',
            'player_a_id' => 'p_1',
            'reason' => 'CHAT_TOXICITY',
            'severity' => 'warning',
            'status' => 'pending',
        ]);

        $res = $this->actingAs($this->admin)->post("/admin/moderation/activities/{$act->id}/dismiss");
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $act->refresh();
        $this->assertEquals('dismissed', $act->status);
    }
}
