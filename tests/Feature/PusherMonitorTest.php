<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PusherMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::resetStats();
        PusherService::fake();

        $this->adminUser = User::create([
            'username' => 'pusher_admin',
            'name' => 'Pusher Admin Tester',
            'email' => 'pusher_admin@deceit.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_guest_cannot_access_pusher_monitor(): void
    {
        $response = $this->get('/admin/pusher');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_pusher_monitor_page(): void
    {
        // Record some dummy transmissions before loading
        PusherService::trigger('room-TEST01', 's_room_snapshot', ['players' => 4]);
        PusherService::trigger('room-TEST01', 's_phase_changed', ['phase' => 'NIGHT']);

        $response = $this->actingAs($this->adminUser)->get('/admin/pusher');
        $response->assertStatus(200);
        $response->assertSee('مراقبة استهلاك واتصالات Pusher Channels');
        $response->assertSee('الاستهلاك اليومي (Messages)');
        $response->assertSee('Cluster:');
        $response->assertSee('App ID:');
        $response->assertSee('s_room_snapshot');
        $response->assertSee('s_phase_changed');
    }

    public function test_live_stats_endpoint_returns_json_metrics(): void
    {
        PusherService::trigger('room-LIVE99', 's_chat_message', ['text' => 'Hello live monitor']);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/pusher/live-stats');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'stats' => [
                'config' => ['app_id', 'cluster', 'masked_key', 'daily_limit'],
                'today' => ['messages', 'bytes', 'bytes_formatted', 'quota_percentage', 'remaining'],
                'totals' => ['messages', 'bytes', 'bytes_formatted', 'errors', 'success_rate'],
                'active_rooms_count',
                'events_breakdown',
                'channels_breakdown',
                'recent_transmissions',
            ],
            'channelsApi',
            'timestamp',
        ]);

        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertGreaterThanOrEqual(1, $json['stats']['today']['messages']);
        $this->assertArrayHasKey('s_chat_message', $json['stats']['events_breakdown']);
    }

    public function test_pusher_ping_endpoint_returns_connectivity_status(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/pusher/ping');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'latency_ms',
            'status_code',
            'cluster',
            'app_id',
            'timestamp',
        ]);
    }

    public function test_admin_can_send_test_broadcast_event(): void
    {
        $payload = [
            'channel' => 'room-MONITOR',
            'event'   => 's_admin_ping',
            'message' => 'Automated test broadcast verification',
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/admin/pusher/test-event', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'channel' => 'room-MONITOR',
            'event'   => 's_admin_ping',
        ]);

        // Verify PusherService broadcast assertion
        PusherService::assertBroadcast('room-MONITOR', 's_admin_ping');

        // Verify Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action'  => 'PUSHER_TEST_EVENT',
        ]);
    }

    public function test_channel_inspect_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/pusher/inspect-channel?channel=room-TEST123');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'channel',
        ]);
        $this->assertEquals('room-TEST123', $response->json('channel'));
    }

    public function test_admin_can_reset_pusher_stats(): void
    {
        // Generate some transmissions
        PusherService::trigger('room-RESET1', 's_room_snapshot', ['sample' => true]);
        $statsBefore = PusherService::getUsageStats();
        $this->assertGreaterThan(0, $statsBefore['today']['messages']);

        // Reset stats
        $response = $this->actingAs($this->adminUser)->postJson('/admin/pusher/reset-stats');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $statsAfter = PusherService::getUsageStats();
        $this->assertEquals(0, $statsAfter['today']['messages']);
        $this->assertEquals(0, $statsAfter['totals']['messages']);
        $this->assertEmpty($statsAfter['recent_transmissions']);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action'  => 'RESET_PUSHER_STATS',
        ]);
    }

    public function test_format_bytes_utility_accurately_formats_data_units(): void
    {
        $this->assertEquals('0 B', PusherService::formatBytes(0));
        $this->assertEquals('500 B', PusherService::formatBytes(500));
        $this->assertEquals('1.5 KB', PusherService::formatBytes(1536));
        $this->assertEquals('2 MB', PusherService::formatBytes(2097152));
    }
}
