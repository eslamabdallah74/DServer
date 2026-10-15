<?php

namespace App\Console\Commands;

use App\Services\GameEngine\RoomManager;
use Illuminate\Console\Command;

class GameTickCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:tick {--daemon : Run continuously as a background worker} {--interval=1 : Sleep interval in seconds for daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active game rooms and advance expired phase timers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDaemon = (bool)$this->option('daemon');
        $interval = max(1, (int)$this->option('interval'));

        $this->info("Game Tick Worker started. Mode: " . ($isDaemon ? "Daemon (interval {$interval}s)" : "One-shot"));

        do {
            $this->runTick();

            if ($isDaemon) {
                sleep($interval);
            }
        } while ($isDaemon);

        return Command::SUCCESS;
    }

    /**
     * Run a single tick cycle across all active rooms.
     */
    private function runTick(): void
    {
        $activeRooms = RoomManager::getAllActiveRooms();
        $now = (int)(microtime(true) * 1000);

        foreach ($activeRooms as $room) {
            $code = $room['roomCode'] ?? null;
            if (!$code) {
                continue;
            }

            $phase = $room['phase'] ?? 'LOBBY';
            $endsAt = $room['phaseEndsAt'] ?? 0;

            // Advance phase if timer has expired
            if ($phase !== 'LOBBY' && $phase !== 'GAME_OVER' && $endsAt > 0 && $now >= $endsAt) {
                $this->line("Room [{$code}] phase [{$phase}] expired (timer: {$endsAt}, now: {$now}). Advancing...");
                RoomManager::checkAndAdvanceExpiredPhase($room);
            }

            // Cleanup rooms completely abandoned (> 4 hours without activity)
            $lastActivity = $room['lastActivityAt'] ?? $room['createdAt'] ?? 0;
            if ($now - $lastActivity > (4 * 3600 * 1000)) {
                $this->warn("Room [{$code}] stale for > 4 hours. Deleting...");
                RoomManager::deleteRoom($code);
            }
        }
    }
}
