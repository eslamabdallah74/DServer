<?php

namespace App\Services\GameEngine;

use App\Services\PusherService;
use Illuminate\Support\Str;

class ChatEngine
{
    public static function processMessage(array &$room, array $player, string $text): array
    {
        $cleanText = trim($text);
        if (empty($cleanText)) {
            return ['error' => 'EMPTY_MESSAGE'];
        }

        // Check if player is silenced
        $statuses = $player['statuses'] ?? [];
        if (in_array('SILENCED', $statuses, true)) {
            return ['error' => 'PLAYER_SILENCED'];
        }

        // Check if dead player trying to talk during match
        $phase = $room['phase'] ?? 'LOBBY';
        if ($phase !== 'LOBBY' && !($player['isAlive'] ?? true)) {
            return ['error' => 'DEAD_PLAYERS_CANNOT_CHAT'];
        }

        $now = (int)(microtime(true) * 1000);
        $messageId = 'msg_' . substr(Str::uuid()->toString(), 0, 8);

        $message = [
            'id' => $messageId,
            'messageId' => $messageId,
            'playerId' => $player['playerId'],
            'nickname' => $player['nickname'],
            'text' => $cleanText,
            'timestamp' => $now,
            'createdAt' => $now,
            'channel' => 'PUBLIC',
        ];

        $room['chatHistory'] = $room['chatHistory'] ?? [];
        $room['chatHistory'][] = $message;

        // Keep last 100 messages in memory
        if (count($room['chatHistory']) > 100) {
            array_shift($room['chatHistory']);
        }

        // Broadcast to Pusher channel
        PusherService::broadcastToRoom($room['roomCode'], 's_chat_message', $message);

        return ['success' => true, 'message' => $message];
    }
}
