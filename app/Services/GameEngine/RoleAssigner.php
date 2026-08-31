<?php

namespace App\Services\GameEngine;

class RoleAssigner
{
    public static function assignRoles(array $players): array
    {
        $count = count($players);
        if ($count < 3) return $players;

        // Determine Shadow count based on player total
        $shadowCount = 1;
        if ($count >= 6) $shadowCount = 2;
        if ($count >= 9) $shadowCount = 3;

        $rolesPool = [];

        // Add Shadow roles
        $rolesPool[] = ['role' => 'shadow', 'faction' => 'SHADOWS'];
        if ($shadowCount >= 2) $rolesPool[] = ['role' => 'terrorist', 'faction' => 'SHADOWS'];
        if ($shadowCount >= 3) $rolesPool[] = ['role' => 'spy', 'faction' => 'SHADOWS'];

        // Add Doctor & Detective
        $rolesPool[] = ['role' => 'doctor', 'faction' => 'KINGDOM'];
        $rolesPool[] = ['role' => 'detective', 'faction' => 'KINGDOM'];

        // Fill remaining with Kingdom Innocent / Guard
        while (count($rolesPool) < $count) {
            $rolesPool[] = ['role' => 'innocent', 'faction' => 'KINGDOM'];
        }

        shuffle($rolesPool);
        shuffle($players);

        foreach ($players as $index => &$player) {
            $assigned = $rolesPool[$index];
            $player['role'] = $assigned['role'];
            $player['faction'] = $assigned['faction'];
            $player['isAlive'] = true;
        }

        return $players;
    }
}
