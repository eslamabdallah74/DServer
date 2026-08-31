<?php

namespace App\Services\GameEngine;

class BotEngine
{
    private static array $botNames = [
        'الفارس خالد', 'الساحرة مريم', 'الحارس سيف', 'الظل الأخير',
        'القاضي عمر', 'الجاسوس سامي', 'الوزير زياد', 'أميرة النور'
    ];

    public static function fillWithBots(array $players, int $targetCount = 6): array
    {
        $currentCount = count($players);
        if ($currentCount >= $targetCount) return $players;

        $existingNames = array_column($players, 'nickname');
        $botIndex = 1;

        while (count($players) < $targetCount) {
            $name = self::$botNames[array_rand(self::$botNames)] . ' ' . rand(10, 99);
            while (in_array($name, $existingNames)) {
                $name = self::$botNames[array_rand(self::$botNames)] . ' ' . rand(10, 99);
            }
            $existingNames[] = $name;

            $botId = 'bot_' . uniqid() . '_' . $botIndex++;
            $players[] = [
                'playerId' => $botId,
                'nickname' => $name,
                'isHost' => false,
                'isReady' => true,
                'isBot' => true,
                'isAlive' => true,
            ];
        }

        return $players;
    }
}
