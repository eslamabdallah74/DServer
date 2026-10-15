<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\GameEngine\RoomManager;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();

        $this->adminUser = User::create([
            'username' => 'admin_test',
            'name' => 'Admin Tester',
            'email' => 'admin@deceit.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_login_and_view_dashboard(): void
    {
        $loginRes = $this->post('/admin/login', [
            'login' => 'admin_test',
            'password' => 'secret123',
        ]);

        $loginRes->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->adminUser);

        // View main dashboard
        $dashRes = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $dashRes->assertStatus(200);
        $dashRes->assertSee('لوحة القيادة والمراقبة المباشرة');
    }

    public function test_admin_can_access_all_screens(): void
    {
        $admin = $this->adminUser;

        $this->actingAs($admin)->get('/admin/rooms')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/analytics')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/players')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/issues')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/feedback')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/audit-logs')->assertStatus(200);
    }

    public function test_admin_economy_and_ban_management(): void
    {
        $player = PlayerProfile::create([
            'player_id' => 'p_12345',
            'nickname' => 'الأسطورة',
            'coins' => 100,
            'is_banned' => false,
        ]);

        $pid = $player->player_id;

        // Update coins
        $coinRes = $this->actingAs($this->adminUser)->post("/admin/players/{$pid}/coins", [
            'coins' => 750,
        ]);
        $coinRes->assertSessionHas('success');
        $this->assertEquals(750, $player->fresh()->coins);

        // Toggle ban to true
        $banRes = $this->actingAs($this->adminUser)->post("/admin/players/{$pid}/ban");
        $banRes->assertSessionHas('success');
        $this->assertTrue($player->fresh()->is_banned);

        // Toggle ban to false
        $unbanRes = $this->actingAs($this->adminUser)->post("/admin/players/{$pid}/ban");
        $unbanRes->assertSessionHas('success');
        $this->assertFalse($player->fresh()->is_banned);
    }

    public function test_admin_broadcast(): void
    {
        $res = $this->actingAs($this->adminUser)->post('/admin/broadcast', [
            'message' => 'تنبيه عاجل: سيتم إجراء صيانة بعد 10 دقائق.'
        ]);

        $res->assertSessionHas('success');
    }

    public function test_admin_live_room_controls(): void
    {
        // Create an active room
        $roomData = RoomManager::createRoom('مستضيف');
        $roomCode = $roomData['room']['roomCode'];

        // Add bot via admin
        $addBotRes = $this->actingAs($this->adminUser)->post("/admin/rooms/{$roomCode}/add-bot");
        $addBotRes->assertSessionHas('success');

        $room = RoomManager::getRoom($roomCode);
        $this->assertCount(2, $room['players']);

        // Destroy room via admin
        $destroyRes = $this->actingAs($this->adminUser)->post("/admin/rooms/{$roomCode}/destroy");
        $destroyRes->assertSessionHas('success');

        $this->assertNull(RoomManager::getRoom($roomCode));
    }
}
