<?php

namespace App\Services\GameEngine;

use Illuminate\Support\Str;

class BotEngine
{
    private static array $botNames = [
        'بوت سعيد', 'بوت علياء', 'بوت كريم', 'بوت نورة',
        'بوت طارق', 'بوت ريم', 'بوت ماجد', 'بوت سارة',
        'بوت فهد', 'بوت ياسمين', 'بوت حمزة', 'بوت ليلى'
    ];

    public static function getRandomBotName(array $existingNames = []): string
    {
        $available = array_values(array_diff(self::$botNames, $existingNames));
        if (!empty($available)) {
            return $available[array_rand($available)];
        }
        return self::$botNames[array_rand(self::$botNames)] . ' ' . rand(10, 99);
    }

    public static function createBot(array $existingNames = []): array
    {
        $name = self::getRandomBotName($existingNames);
        $botId = 'bot_' . substr(Str::uuid()->toString(), 0, 8);

        return [
            'playerId' => $botId,
            'nickname' => $name,
            'isHost' => false,
            'isReady' => true,
            'isBot' => true,
            'isAlive' => true,
            'isConnected' => true,
            'socketId' => null,
            'reconnectTokenHash' => null,
            'role' => null,
            'faction' => null,
            'statuses' => [],
            'tasks' => [],
            'completedTasks' => 0,
            'nightAction' => null,
            'vote' => null,
            'hasNightAction' => false,
        ];
    }

    public static function fillWithBots(array $players, int $targetCount = 4): array
    {
        $currentCount = count($players);
        if ($currentCount >= $targetCount) return $players;

        $existingNames = array_column($players, 'nickname');
        while (count($players) < $targetCount) {
            $bot = self::createBot($existingNames);
            $existingNames[] = $bot['nickname'];
            $players[] = $bot;
        }

        return $players;
    }

    /**
     * Executes bot night actions automatically for living bots.
     */
    public static function handleBotNightActions(array &$room): void
    {
        $players = &$room['players'];
        $livingPlayers = array_values(array_filter($players, fn($p) => $p['isAlive'] ?? true));

        foreach ($players as &$bot) {
            if (!($bot['isBot'] ?? false) || !($bot['isAlive'] ?? true)) {
                continue;
            }

            $bot['isReady'] = true;
            $targetId = null;

            $faction = $bot['faction'] ?? RoleData::getFaction($bot['role'] ?? '');
            $role = $bot['role'] ?? '';

            if ($faction === 'shadow') {
                $nonShadows = array_values(array_filter($livingPlayers, fn($p) => ($p['faction'] ?? RoleData::getFaction($p['role'] ?? '')) !== 'shadow'));
                if (!empty($nonShadows)) {
                    $targetId = $nonShadows[array_rand($nonShadows)]['playerId'];
                }
            } elseif ($role === 'doctor') {
                if (!empty($livingPlayers)) {
                    $targetId = $livingPlayers[array_rand($livingPlayers)]['playerId'];
                }
            } elseif (in_array($role, ['guard', 'royal_guard', 'knight'], true)) {
                $others = array_values(array_filter($livingPlayers, fn($p) => $p['playerId'] !== $bot['playerId']));
                if (!empty($others)) {
                    $targetId = $others[array_rand($others)]['playerId'];
                }
            } elseif (in_array($role, ['investigator', 'minister', 'spy'], true)) {
                $others = array_values(array_filter($livingPlayers, fn($p) => $p['playerId'] !== $bot['playerId']));
                if (!empty($others)) {
                    $targetId = $others[array_rand($others)]['playerId'];
                }
            }

            if ($targetId) {
                $room['nightActions'][$bot['playerId']] = [
                    'actorPlayerId' => $bot['playerId'],
                    'roleId' => $role,
                    'targetPlayerId' => $targetId,
                    'targetPlayerIds' => [$targetId],
                    'submittedAt' => (int)(microtime(true) * 1000),
                ];
            }
        }
    }

    /**
     * Executes bot voting actions automatically for living bots.
     */
    public static function handleBotVoting(array &$room): void
    {
        $players = &$room['players'];
        $livingPlayers = array_values(array_filter($players, fn($p) => $p['isAlive'] ?? true));

        foreach ($players as &$bot) {
            if (!($bot['isBot'] ?? false) || !($bot['isAlive'] ?? true)) {
                continue;
            }

            $bot['isReady'] = true;
            $others = array_values(array_filter($livingPlayers, fn($p) => $p['playerId'] !== $bot['playerId']));
            if (!empty($others)) {
                $target = $others[array_rand($others)]['playerId'];
                $room['votes'][$bot['playerId']] = $target;
            }
        }
    }
}
