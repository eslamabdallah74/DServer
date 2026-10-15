<?php

namespace App\Services\GameEngine;

use App\Services\PusherService;
use Illuminate\Support\Facades\Log;

class StressTestService
{
    /**
     * Run simulated concurrent bot matches and measure performance metrics.
     *
     * @param int $roomCount Number of matches to spawn (e.g. 5, 10)
     * @param int $botsPerRoom Total players per match (e.g. 6)
     * @return array Simulation result metrics
     */
    public static function runSimulation(int $roomCount = 5, int $botsPerRoom = 6): array
    {
        $startTime = microtime(true);
        $startMem = memory_get_usage(true);

        $roomCount = max(1, min(25, $roomCount));
        $botsPerRoom = max(3, min(16, $botsPerRoom));

        $rooms = [];
        $actionsProcessed = 0;

        // Step 1: Spawn rooms and add bots
        for ($i = 0; $i < $roomCount; $i++) {
            $created = RoomManager::createRoom("مضيف_محاكاة_{$i}");
            $code = $created['room']['roomCode'];
            $hostId = $created['hostPlayer']['playerId'];

            // Add remaining players as bots
            for ($b = 1; $b < $botsPerRoom; $b++) {
                RoomManager::addBot($code);
                $actionsProcessed++;
            }

            $rooms[] = [
                'code' => $code,
                'hostId' => $hostId,
            ];
        }

        // Step 2: Start game & progress through game loop
        $roomReports = [];
        foreach ($rooms as $r) {
            $code = $r['code'];
            $hostId = $r['hostId'];

            // Start game
            $startResult = RoomManager::startGame($code, $hostId);
            $actionsProcessed++;

            $activeRoom = RoomManager::getRoom($code);

            // Fast forward from ROLE_REVEAL into NIGHT
            RoomManager::advanceMatchLoop($activeRoom);
            $actionsProcessed++;

            // Submit night actions for alive night-active players
            $livingPlayers = array_filter($activeRoom['players'] ?? [], fn($p) => $p['isAlive'] ?? true);
            $livingIds = array_column($livingPlayers, 'playerId');

            foreach ($livingPlayers as $p) {
                if ($p['hasNightAction'] ?? false) {
                    $otherIds = array_values(array_filter($livingIds, fn($id) => $id !== $p['playerId']));
                    if (!empty($otherIds)) {
                        $targetId = $otherIds[array_rand($otherIds)];
                        RoomManager::submitNightAction($code, $p['playerId'], $targetId);
                        $actionsProcessed++;
                    }
                }
            }

            // Resolve Night into Morning
            $activeRoom = RoomManager::getRoom($code);
            RoomManager::advanceMatchLoop($activeRoom);
            $actionsProcessed++;

            $finalState = RoomManager::getRoom($code);
            $roomReports[] = [
                'code'           => $code,
                'phase'          => $finalState['phase'] ?? 'UNKNOWN',
                'round'          => $finalState['round'] ?? 1,
                'playerCount'    => count($finalState['players'] ?? []),
                'eliminated'     => $finalState['lastNightOutcome']['eliminated']['nickname'] ?? 'لا يوجد قتلى',
                'victoryDecided' => !empty($finalState['victoryOutcome']['isDecided']),
            ];
        }

        $endTime = microtime(true);
        $peakMem = memory_get_peak_usage(true);

        $executionTimeMs = round(($endTime - $startTime) * 1000, 2);
        $memoryUsedMb = round(($peakMem - $startMem) / (1024 * 1024), 2);
        $totalMemoryPeakMb = round($peakMem / (1024 * 1024), 2);

        return [
            'success'            => true,
            'roomsSpawned'       => $roomCount,
            'botsPerRoom'        => $botsPerRoom,
            'totalPlayers'       => $roomCount * $botsPerRoom,
            'actionsProcessed'   => $actionsProcessed,
            'executionTimeMs'    => $executionTimeMs,
            'executionTimeSec'   => round($executionTimeMs / 1000, 2),
            'memoryUsedMb'       => max(0.01, $memoryUsedMb),
            'totalMemoryPeakMb'  => $totalMemoryPeakMb,
            'rooms'              => $roomReports,
        ];
    }
}
