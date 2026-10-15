<?php

namespace App\Services\GameEngine;

class RoleAssigner
{
    /**
     * Assigns roles to players based on player count using exact composition pool.
     */
    public static function assignRoles(array &$room): void
    {
        $players = &$room['players'];
        $count = count($players);
        $pool = RoleData::getCompositionPool($count);

        // Filter out globally disabled roles
        $disabledRoles = GameConfigService::getDisabledRoles();
        if (!empty($disabledRoles)) {
            foreach ($pool as $idx => $role) {
                if (in_array($role, $disabledRoles, true) && $role !== 'king') {
                    $faction = RoleData::getFaction($role);
                    if ($faction === 'shadow') {
                        $pool[$idx] = 'assassin';
                    } else {
                        $pool[$idx] = 'citizen';
                    }
                }
            }
        }

        // Fisher-Yates shuffle on pool
        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = rand(0, $i);
            $tmp = $pool[$i];
            $pool[$i] = $pool[$j];
            $pool[$j] = $tmp;
        }

        foreach ($players as $idx => &$player) {
            $role = $pool[$idx];
            $player['role'] = $role;
            $player['faction'] = RoleData::getFaction($role);
            $player['isAlive'] = true;
            $player['statuses'] = [];
            $player['completedTasks'] = 0;
            $player['nightAction'] = null;
            $player['vote'] = null;
            $player['hasNightAction'] = RoleData::hasNightAction($role);
        }

        // Reset match properties on room
        $room['round'] = 0;
        $room['taskProgress'] = 0;
        $room['vacantThrone'] = false;
        $room['lastEliminatedPlayer'] = null;
        $room['lastEliminatedCause'] = null;
        $room['lastNightOutcome'] = null;
        $room['victoryOutcome'] = null;
        $room['darknessActive'] = false;
    }
}
