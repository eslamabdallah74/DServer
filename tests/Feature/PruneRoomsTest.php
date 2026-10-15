<?php

namespace Tests\Feature;

use App\Services\GameEngine\RoomManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_rooms_older_than_threshold(): void
    {
        // Create an old room (3 hours ago)
        $oldRes = RoomManager::createRoom('قديم');
        $oldCode = $oldRes['room']['roomCode'];

        DB::table('active_rooms')->where('room_code', $oldCode)->update([
            'last_activity_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        // Create a recent room (10 minutes ago)
        $freshRes = RoomManager::createRoom('حديث');
        $freshCode = $freshRes['room']['roomCode'];

        // Run the prune command with 2-hour threshold
        $this->artisan('rooms:prune', ['--hours' => 2])
            ->expectsOutputToContain('Successfully pruned 1 inactive room(s) older than 2 hour(s).')
            ->assertExitCode(0);

        // Verify old room was pruned
        $this->assertNull(RoomManager::getRoom($oldCode));
        $this->assertDatabaseMissing('active_rooms', ['room_code' => $oldCode]);

        // Verify fresh room remains intact
        $this->assertNotNull(RoomManager::getRoom($freshCode));
        $this->assertDatabaseHas('active_rooms', ['room_code' => $freshCode]);
    }
}
