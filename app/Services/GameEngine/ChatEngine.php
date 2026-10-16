<?php

namespace App\Services\GameEngine;

use App\Models\PlayerProfile;
use App\Services\Moderation\AntiCheatEngine;
use App\Services\PusherService;
use Illuminate\Support\Str;

class ChatEngine
{
    public static function processMessage(array &$room, array $player, string $text, string $channel = 'PUBLIC'): array
    {
        $cleanText = trim($text);
        if (empty($cleanText)) {
            return ['error' => 'EMPTY_MESSAGE'];
        }

        // Check if player profile is muted by administration
        $profile = PlayerProfile::where('player_id', $player['playerId'])
            ->orWhere('nickname', $player['nickname'])
            ->first();
        if ($profile && $profile->isMuted()) {
            return [
                'error' => 'PLAYER_MUTED',
                'message' => 'أنت ممنوع مؤقتاً من الدردشة بواسطة المشرف.',
                'mutedUntil' => $profile->muted_until?->toIso8601String(),
            ];
        }

        // Check if player is silenced by game role
        $statuses = $player['statuses'] ?? [];
        if (in_array('SILENCED', $statuses, true)) {
            return ['error' => 'PLAYER_SILENCED'];
        }

        $targetChannel = strtoupper(trim($channel));
        if (!in_array($targetChannel, ['PUBLIC', 'GHOST'], true)) {
            $targetChannel = 'PUBLIC';
        }

        // Check if dead player trying to talk during match
        $phase = $room['phase'] ?? 'LOBBY';
        $isAlive = $player['isAlive'] ?? true;
        if ($phase !== 'LOBBY' && !$isAlive) {
            if ($targetChannel !== 'GHOST') {
                return ['error' => 'DEAD_PLAYERS_CANNOT_CHAT'];
            }
        }

        // Living players cannot use ghost chat during game
        if ($phase !== 'LOBBY' && $isAlive && $targetChannel === 'GHOST') {
            return ['error' => 'ALIVE_PLAYERS_CANNOT_USE_GHOST_CHAT'];
        }

        // Automated toxicity & role leak watchdog
        $toxicity = AntiCheatEngine::checkChatToxicity($cleanText, $player['playerId'], $room['roomCode'] ?? null);
        if ($toxicity['toxic'] ?? false) {
            return [
                'error' => 'MESSAGE_BLOCKED_TOXIC',
                'message' => 'تم حجب الرسالة لمخالفتها الآداب العامة وقواعد اللعبة.',
                'reason' => $toxicity['reason'],
            ];
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
            'channel' => ($phase === 'LOBBY') ? 'PUBLIC' : $targetChannel,
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

