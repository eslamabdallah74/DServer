<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GameEngine\StressTestService;
use App\Services\PusherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StressSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        PusherService::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_access_stress_test_page(): void
    {
        $response = $this->get('/admin/stress-test');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_stress_test_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/stress-test');
        $response->assertStatus(200);
        $response->assertSee('محاكي الضغط وتوليد المباريات الوهمية');
    }

    public function test_admin_can_trigger_stress_test_simulation(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/stress-test/run', [
            'roomCount' => 3,
            'botsPerRoom' => 6,
        ]);

        $response->assertRedirect('/admin/stress-test');
        $response->assertSessionHas('success');
        $response->assertSessionHas('stress_test_result');

        $result = session('stress_test_result');
        $this->assertEquals(3, $result['roomsSpawned']);
        $this->assertEquals(6, $result['botsPerRoom']);
        $this->assertEquals(18, $result['totalPlayers']);
        $this->assertCount(3, $result['rooms']);
        $this->assertGreaterThan(0, $result['actionsProcessed']);
    }

    public function test_can_spawn_and_run_ten_concurrent_bot_matches(): void
    {
        $result = StressTestService::runSimulation(10, 6);

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['roomsSpawned']);
        $this->assertEquals(60, $result['totalPlayers']);
        $this->assertCount(10, $result['rooms']);
        $this->assertGreaterThan(50, $result['actionsProcessed']);
        $this->assertGreaterThan(0, $result['executionTimeMs']);
    }
}
