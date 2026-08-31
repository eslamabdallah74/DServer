<?php

namespace App\Services\GameEngine;

class NightResolver
{
    public static function resolveNight(array $players, array $nightActions): array
    {
        $targetKills = [];
        $targetHeals = [];
        $inspections = [];

        foreach ($nightActions as $action) {
            $role = $action['role'] ?? '';
            $targetId = $action['targetId'] ?? null;

            if (!$targetId) continue;

            if ($role === 'shadow' || $role === 'terrorist') {
                $targetKills[] = $targetId;
            } elseif ($role === 'doctor') {
                $targetHeals[] = $targetId;
            } elseif ($role === 'detective') {
                $inspections[$action['actorId']] = $targetId;
            }
        }

        $eliminated = [];
        foreach ($targetKills as $targetId) {
            if (!in_array($targetId, $targetHeals)) {
                $eliminated[] = $targetId;
            }
        }

        foreach ($players as &$player) {
            if (in_array($player['playerId'], $eliminated)) {
                $player['isAlive'] = false;
            }
        }

        return [
            'players' => $players,
            'eliminated' => $eliminated,
            'inspections' => $inspections,
        ];
    }
}
