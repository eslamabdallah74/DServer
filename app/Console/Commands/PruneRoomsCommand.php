<?php

namespace App\Console\Commands;

use App\Services\GameEngine\RoomManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRoomsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rooms:prune {--hours=2 : Prune rooms inactive for more than this many hours}';

    /**
     * The console command description.
     */
    protected $description = 'Prune abandoned or inactive game rooms older than the specified hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int)$this->option('hours');
        if ($hours <= 0) {
            $hours = 2;
        }

        $cutoff = Carbon::now()->subHours($hours);

        $staleRooms = DB::table('active_rooms')
            ->where('updated_at', '<', $cutoff)
            ->orWhere('last_activity_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($staleRooms as $room) {
            RoomManager::deleteRoom($room->room_code);
            $count++;
        }

        $this->info("Successfully pruned {$count} inactive room(s) older than {$hours} hour(s).");
        return self::SUCCESS;
    }
}

