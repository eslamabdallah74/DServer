<?php

namespace App\Services\GameEngine;

class VictoryEngine
{
    public static function checkVictory(array $players): ?array
    {
        $alivePlayers = array_filter($players, fn($p) => $p['isAlive'] ?? true);
        if (empty($alivePlayers)) {
            return ['winner' => 'DRAW', 'reason' => 'جميع اللاعبين تم استبعادهم.'];
        }

        $shadowsCount = 0;
        $kingdomCount = 0;
        $jesterWon = false;

        foreach ($alivePlayers as $p) {
            $faction = $p['faction'] ?? 'KINGDOM';
            if ($faction === 'SHADOWS') {
                $shadowsCount++;
            } elseif ($faction === 'KINGDOM') {
                $kingdomCount++;
            }
        }

        if ($shadowsCount === 0) {
            return ['winner' => 'KINGDOM', 'reason' => 'فريق المملكة انتصر بإقصاء جميع الظلال!'];
        }

        if ($shadowsCount >= $kingdomCount) {
            return ['winner' => 'SHADOWS', 'reason' => 'فريق الظلال سيطر على القرية!'];
        }

        return null; // Game continues
    }
}
