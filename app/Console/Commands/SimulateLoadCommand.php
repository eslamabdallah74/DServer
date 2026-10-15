<?php

namespace App\Console\Commands;

use App\Services\GameEngine\StressTestService;
use App\Services\PusherService;
use Illuminate\Console\Command;

class SimulateLoadCommand extends Command
{
    protected $signature = 'test:simulate-load
                            {--rooms=5 : Number of concurrent match rooms to spawn}
                            {--bots-per-room=6 : Total players per room}';

    protected $description = 'Simulate concurrent bot matches and measure server performance and memory footprint';

    public function handle(): int
    {
        $rooms = (int)$this->option('rooms');
        $bots = (int)$this->option('bots-per-room');

        $this->info("🚀 Starting load simulation: {$rooms} rooms with {$bots} players each...");

        PusherService::fake();

        $result = StressTestService::runSimulation($rooms, $bots);

        $this->newLine();
        $this->line("<fg=yellow;options=bold>📊 SIMULATION RESULTS:</>");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rooms Spawned', $result['roomsSpawned']],
                ['Total Simulated Players', $result['totalPlayers']],
                ['Actions & Events Processed', $result['actionsProcessed']],
                ['Total Execution Time', "{$result['executionTimeMs']} ms ({$result['executionTimeSec']}s)"],
                ['Peak Memory Footprint', "{$result['totalMemoryPeakMb']} MB"],
            ]
        );

        $this->info("✅ Simulation completed successfully without errors.");

        return Command::SUCCESS;
    }
}
