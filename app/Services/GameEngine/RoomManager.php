<?php

namespace App\Services\GameEngine;

use App\Models\PlayerProfile;
use App\Models\MatchLog;

class RoomManager
{
    private static array $rooms = [];

    public static function createRoom(string $hostPlayerId, string $hostNickname): array
    {
        $code = self::generateRoomCode();

        $room = [
            'roomCode' => $code,
            'hostId' => $hostPlayerId,
            'phase' => 'LOBBY',
            'phaseEndsAt' => 0,
            'players' => [
                [
                    'playerId' => $hostPlayerId,
                    'nickname' => $hostNickname,
                    'isHost' => true,
                    'isReady' => true,
                    'isBot' => false,
                    'isAlive' => true,
                ]
            ],
            'nightActions' => [],
            'votes' => [],
            'createdAt' => time(),
        ];

        self::$rooms[$code] = $room;
        return $room;
    }

    public static function joinRoom(string $roomCode, string $playerId, string $nickname): ?array
    {
        $code = strtoupper(trim($roomCode));
        if (!isset(self::$rooms[$code])) return null;

        $room = &self::$rooms[$code];

        // Check if player already exists in room
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $playerId) {
                $p['nickname'] = $nickname;
                return $room;
            }
        }

        if (count($room['players']) >= 12 || $room['phase'] !== 'LOBBY') {
            return null;
        }

        $room['players'][] = [
            'playerId' => $playerId,
            'nickname' => $nickname,
            'isHost' => false,
            'isReady' => false,
            'isBot' => false,
            'isAlive' => true,
        ];

        return $room;
    }

    public static function getRoom(string $roomCode): ?array
    {
        $code = strtoupper(trim($roomCode));
        return self::$rooms[$code] ?? null;
    }

    public static function toggleReady(string $roomCode, string $playerId): ?array
    {
        $code = strtoupper(trim($roomCode));
        if (!isset(self::$rooms[$code])) return null;

        $room = &self::$rooms[$code];
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $playerId) {
                $p['isReady'] = !$p['isReady'];
                break;
            }
        }

        return $room;
    }

    public static function startGame(string $roomCode, string $hostId): ?array
    {
        $code = strtoupper(trim($roomCode));
        if (!isset(self::$rooms[$code])) return null;

        $room = &self::$rooms[$code];
        if ($room['hostId'] !== $hostId) return null;

        // Auto fill bots if less than 4 players
        if (count($room['players']) < 4) {
            $room['players'] = BotEngine::fillWithBots($room['players'], 6);
        }

        // Assign Roles
        $room['players'] = RoleAssigner::assignRoles($room['players']);
        $room['phase'] = 'ROLE_REVEAL';
        $room['phaseEndsAt'] = time() + 8;

        return $room;
    }

    public static function sanitizeRoomForPlayer(array $room, string $playerId): array
    {
        $sanitized = $room;
        $localPlayer = null;

        foreach ($room['players'] as $p) {
            if ($p['playerId'] === $playerId) {
                $localPlayer = $p;
                break;
            }
        }

        $localFaction = $localPlayer['faction'] ?? 'KINGDOM';
        $localRole = $localPlayer['role'] ?? 'innocent';

        foreach ($sanitized['players'] as &$p) {
            // Hide role unless game over OR same Shadow team OR self
            if ($room['phase'] !== 'GAME_OVER' && $p['playerId'] !== $playerId) {
                $isShadowAlly = ($localFaction === 'SHADOWS' && ($p['faction'] ?? '') === 'SHADOWS');
                if (!$isShadowAlly) {
                    unset($p['role']);
                    unset($p['faction']);
                }
            }
        }

        return $sanitized;
    }

    private static function generateRoomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
        } while (isset(self::$rooms[$code]));

        return $code;
    }
}
