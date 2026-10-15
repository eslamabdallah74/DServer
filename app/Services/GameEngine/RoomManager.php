<?php

namespace App\Services\GameEngine;

use App\Models\MatchLog;
use App\Services\PusherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RoomManager
{
    private static array $inMemoryRooms = [];

    /**
     * Generate 4-character room code avoiding 0/O and 1/I confusion.
     */
    public static function generateRoomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
        } while (self::getRoom($code) !== null);

        return $code;
    }

    /**
     * Create a new room with host player.
     */
    public static function createRoom(string $hostNickname, array $customSettings = []): array
    {
        $roomCode = self::generateRoomCode();
        $hostPlayerId = 'p_' . substr(Str::uuid()->toString(), 0, 8);
        $rawReconnectToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawReconnectToken);

        $defaultSettings = [
            'maxPlayers'           => 16,
            'dayTimerSeconds'      => 120,
            'nightTimerSeconds'    => 60,
            'votingTimerSeconds'   => 60,
            'tieRule'              => 'NO_ELIMINATION',
            'kingMustSurvive'      => true,
            'revealEliminatedRole' => true,
            'enabledRoleIds'       => [],
        ];

        $now = (int)(microtime(true) * 1000);

        $hostPlayer = [
            'playerId'           => $hostPlayerId,
            'socketId'           => null,
            'nickname'           => trim($hostNickname) ?: 'Host',
            'isHost'             => true,
            'isBot'              => false,
            'isAlive'            => true,
            'isConnected'        => true,
            'isReady'            => true,
            'reconnectTokenHash' => $tokenHash,
            'role'               => null,
            'faction'            => null,
            'statuses'           => [],
            'tasks'              => [],
            'completedTasks'     => 0,
            'nightAction'        => null,
            'vote'               => null,
            'hasNightAction'     => false,
        ];

        $room = [
            'code'                 => $roomCode,
            'roomCode'             => $roomCode,
            'hostPlayerId'         => $hostPlayerId,
            'settings'             => array_merge($defaultSettings, $customSettings),
            'phase'                => 'LOBBY',
            'phaseStartedAt'       => $now,
            'phaseEndsAt'          => 0,
            'createdAt'            => $now,
            'lastActivityAt'       => $now,
            'eventSequence'        => 100,
            'round'                => 0,
            'players'              => [$hostPlayer],
            'nightActions'         => [],
            'votes'                => [],
            'taskProgress'         => 0,
            'darknessActive'       => false,
            'chatHistory'          => [],
            'vacantThrone'         => false,
            'lastEliminatedPlayer' => null,
            'lastEliminatedCause'  => null,
            'lastNightOutcome'     => null,
            'victoryOutcome'       => null,
        ];

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return [
            'room' => $room,
            'hostPlayer' => $hostPlayer,
            'reconnectToken' => $rawReconnectToken,
        ];
    }

    /**
     * Join an existing room.
     */
    public static function joinRoom(string $roomCode, string $nickname): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) {
            return ['error' => 'ROOM_NOT_FOUND'];
        }

        if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') {
            return ['error' => 'GAME_ALREADY_STARTED'];
        }

        $cleanName = trim($nickname) ?: ('لاعب_' . rand(1000, 9999));

        // Deduplicate: If same nickname exists in lobby and is not a bot, replace it
        foreach ($room['players'] as $idx => $p) {
            if ($p['nickname'] === $cleanName && !($p['isBot'] ?? false)) {
                array_splice($room['players'], $idx, 1);
                break;
            }
        }

        $maxPlayers = $room['settings']['maxPlayers'] ?? 16;
        if (count($room['players']) >= $maxPlayers) {
            return ['error' => 'ROOM_FULL'];
        }

        $playerId = 'p_' . substr(Str::uuid()->toString(), 0, 8);
        $rawReconnectToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawReconnectToken);

        $player = [
            'playerId'           => $playerId,
            'socketId'           => null,
            'nickname'           => $cleanName,
            'isHost'             => false,
            'isBot'              => false,
            'isAlive'            => true,
            'isConnected'        => true,
            'isReady'            => false,
            'reconnectTokenHash' => $tokenHash,
            'role'               => null,
            'faction'            => null,
            'statuses'           => [],
            'tasks'              => [],
            'completedTasks'     => 0,
            'nightAction'        => null,
            'vote'               => null,
            'hasNightAction'     => false,
        ];

        $room['players'][] = $player;
        $room['lastActivityAt'] = (int)(microtime(true) * 1000);

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return [
            'room' => $room,
            'player' => $player,
            'reconnectToken' => $rawReconnectToken,
        ];
    }

    /**
     * Add a bot to the room.
     */
    public static function addBot(string $roomCode): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];
        if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') return ['error' => 'GAME_ALREADY_STARTED'];

        $maxPlayers = $room['settings']['maxPlayers'] ?? 16;
        if (count($room['players']) >= $maxPlayers) return ['error' => 'ROOM_FULL'];

        $existingNames = array_column($room['players'], 'nickname');
        $bot = BotEngine::createBot($existingNames);

        $room['players'][] = $bot;
        $room['lastActivityAt'] = (int)(microtime(true) * 1000);

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return ['room' => $room, 'bot' => $bot];
    }

    /**
     * Remove a bot from the room.
     */
    public static function removeBot(string $roomCode, ?string $botPlayerId = null): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];
        if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') return ['error' => 'GAME_ALREADY_STARTED'];

        $botIndex = -1;
        if ($botPlayerId) {
            foreach ($room['players'] as $idx => $p) {
                if (($p['isBot'] ?? false) && $p['playerId'] === $botPlayerId) {
                    $botIndex = $idx;
                    break;
                }
            }
        } else {
            for ($i = count($room['players']) - 1; $i >= 0; $i--) {
                if ($room['players'][$i]['isBot'] ?? false) {
                    $botIndex = $i;
                    break;
                }
            }
        }

        if ($botIndex === -1) {
            return ['error' => 'NO_BOTS_FOUND'];
        }

        array_splice($room['players'], $botIndex, 1);
        $room['lastActivityAt'] = (int)(microtime(true) * 1000);

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return ['room' => $room];
    }

    /**
     * Kick player from room (Host only).
     */
    public static function kickPlayer(string $roomCode, string $hostPlayerId, string $targetPlayerId): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];
        if (($room['hostPlayerId'] ?? '') !== $hostPlayerId) {
            return ['error' => 'NOT_HOST', 'message' => 'وحده المضيف يمكنه طرد اللاعبين'];
        }
        if ($targetPlayerId === $hostPlayerId) {
            return ['error' => 'CANNOT_KICK_SELF'];
        }

        $targetIndex = -1;
        foreach ($room['players'] as $idx => $p) {
            if ($p['playerId'] === $targetPlayerId) {
                $targetIndex = $idx;
                break;
            }
        }

        if ($targetIndex === -1) {
            return ['error' => 'PLAYER_NOT_FOUND'];
        }

        $targetPlayer = $room['players'][$targetIndex];
        if ($targetPlayer['isBot'] ?? false) {
            return self::removeBot($roomCode, $targetPlayerId);
        }

        array_splice($room['players'], $targetIndex, 1);
        PusherService::broadcastToRoom($code, 's_kicked', [
            'targetPlayerId' => $targetPlayerId,
            'reason' => 'تم طردك من الغرفة بواسطة مشرف الغرفة.',
        ]);

        $room['lastActivityAt'] = (int)(microtime(true) * 1000);
        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return ['room' => $room];
    }

    /**
     * Player leaves room.
     */
    public static function leaveRoom(string $roomCode, string $playerId): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];

        $playerIndex = -1;
        foreach ($room['players'] as $idx => $p) {
            if ($p['playerId'] === $playerId) {
                $playerIndex = $idx;
                break;
            }
        }

        if ($playerIndex === -1) return ['error' => 'PLAYER_NOT_FOUND'];

        $leavingPlayer = $room['players'][$playerIndex];
        array_splice($room['players'], $playerIndex, 1);

        // Reassign host if host left
        if (($leavingPlayer['isHost'] ?? false) && count($room['players']) > 0) {
            $nextHuman = null;
            foreach ($room['players'] as &$p) {
                if (!($p['isBot'] ?? false)) {
                    $nextHuman = &$p;
                    break;
                }
            }
            if ($nextHuman) {
                $nextHuman['isHost'] = true;
                $room['hostPlayerId'] = $nextHuman['playerId'];
            }
        }

        // Check if any human players remain
        $humanCount = count(array_filter($room['players'], fn($p) => !($p['isBot'] ?? false)));
        if ($humanCount === 0) {
            self::deleteRoom($code);
            PusherService::broadcastToRoom($code, 's_room_destroyed', [
                'reason' => 'No human players remaining.'
            ]);
            return ['roomDestroyed' => true];
        }

        $room['lastActivityAt'] = (int)(microtime(true) * 1000);
        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return ['room' => $room, 'roomDestroyed' => false];
    }

    /**
     * Toggle or set ready status for a player.
     */
    public static function toggleReady(string $roomCode, string $playerId, ?bool $isReady = null, ?string $nickname = null): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];

        $targetPlayer = null;
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $playerId || str_ends_with($p['playerId'], $playerId)) {
                $targetPlayer = &$p;
                break;
            }
        }

        if (!$targetPlayer && ($nickname || $playerId)) {
            $searchKey = trim($nickname ?: $playerId);
            foreach ($room['players'] as &$p) {
                if (trim($p['nickname']) === $searchKey) {
                    $targetPlayer = &$p;
                    break;
                }
            }
        }

        if (!$targetPlayer) {
            return ['error' => 'PLAYER_NOT_FOUND'];
        }

        if ($targetPlayer['isAlive'] ?? true) {
            $targetPlayer['isReady'] = ($isReady !== null) ? (bool)$isReady : !($targetPlayer['isReady'] ?? false);
            self::saveRoom($room);
            self::broadcastSnapshot($room);

            // If match is active, check if all living players are ready
            if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') {
                self::checkAllReady($room);
            }
        }

        return ['success' => true, 'isReady' => $targetPlayer['isReady'], 'room' => $room];
    }

    /**
     * Start the match (Host only).
     */
    public static function startGame(string $roomCode, string $hostPlayerId): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];
        if (($room['hostPlayerId'] ?? '') !== $hostPlayerId) return ['error' => 'NOT_HOST'];

        if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') {
            return ['error' => 'ALREADY_STARTED', 'message' => 'اللعبة بدأت بالفعل'];
        }

        if (count($room['players']) < 2) {
            return ['error' => 'NOT_ENOUGH_PLAYERS', 'message' => 'يلزم لاعبان على الأقل لبدء المباراة'];
        }

        // Enforce all human non-host players are ready
        $notReady = array_filter($room['players'], function ($p) {
            return !($p['isReady'] ?? false) && !($p['isBot'] ?? false) && !($p['isHost'] ?? false);
        });

        if (count($notReady) > 0) {
            return [
                'error' => 'NOT_ALL_PLAYERS_READY',
                'message' => 'يجب أن يكون جميع اللاعبين (جاهز) لبدء المباراة.'
            ];
        }

        // Assign roles and start ROLE_REVEAL phase
        RoleAssigner::assignRoles($room);
        self::startPhase($room, 'ROLE_REVEAL', 8);

        return ['success' => true, 'room' => $room];
    }

    /**
     * Submit a night action.
     */
    public static function submitNightAction(string $roomCode, string $playerId, string $targetPlayerId, array $targetPlayerIds = []): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];

        $player = null;
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $playerId) {
                $player = &$p;
                break;
            }
        }

        if (!$player || !($player['isAlive'] ?? true)) {
            return ['error' => 'INVALID_PLAYER'];
        }

        $ids = !empty($targetPlayerIds) ? $targetPlayerIds : (!empty($targetPlayerId) ? [$targetPlayerId] : []);

        $room['nightActions'][$playerId] = [
            'actorPlayerId'   => $playerId,
            'roleId'          => $player['role'] ?? '',
            'targetPlayerId'  => $ids[0] ?? '',
            'targetPlayerIds' => $ids,
            'submittedAt'     => (int)(microtime(true) * 1000),
        ];

        $player['isReady'] = true;
        self::saveRoom($room);
        self::broadcastSnapshot($room);
        self::checkAllReady($room);

        return ['success' => true];
    }

    /**
     * Cast vote during VOTING phase.
     */
    public static function castVote(string $roomCode, string $playerId, ?string $targetPlayerId): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND'];

        $player = null;
        foreach ($room['players'] as &$p) {
            if ($p['playerId'] === $playerId) {
                $player = &$p;
                break;
            }
        }

        if (!$player || !($player['isAlive'] ?? true)) {
            return ['error' => 'INVALID_PLAYER'];
        }

        if ($targetPlayerId) {
            $room['votes'][$playerId] = $targetPlayerId;
        } else {
            unset($room['votes'][$playerId]);
        }

        // Tally votes
        $voteTally = [];
        foreach ($room['votes'] as $voter => $target) {
            $voteTally[$target] = ($voteTally[$target] ?? 0) + 1;
        }

        $totalLiving = count(array_filter($room['players'], fn($p) => $p['isAlive'] ?? true));
        PusherService::broadcastToRoom($code, 's_vote_update', [
            'voteTally' => $voteTally,
            'totalVoters' => $totalLiving,
        ]);

        $player['isReady'] = true;
        self::saveRoom($room);
        self::broadcastSnapshot($room);
        self::checkAllReady($room);

        return ['success' => true];
    }

    /**
     * Start a named phase with a timer.
     */
    public static function startPhase(array &$room, string $phaseName, int $durationSeconds): void
    {
        $now = (int)(microtime(true) * 1000);
        $room['phase'] = $phaseName;
        $room['phaseStartedAt'] = $now;
        $room['phaseEndsAt'] = $durationSeconds > 0 ? ($now + $durationSeconds * 1000) : 0;
        $room['eventSequence'] = ($room['eventSequence'] ?? 100) + 1;

        // Reset ready state for all players
        foreach ($room['players'] as &$p) {
            $p['isReady'] = ($p['isBot'] ?? false) ? true : false;
        }

        PusherService::broadcastToRoom($room['roomCode'], 's_phase_changed', [
            'eventSequence'  => $room['eventSequence'],
            'phase'          => $room['phase'],
            'phaseStartedAt' => $room['phaseStartedAt'],
            'phaseEndsAt'    => $room['phaseEndsAt'],
            'round'          => $room['round'] ?? 0,
            'serverTime'     => $now,
        ]);

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        // Execute bot actions for phase
        if ($phaseName === 'NIGHT') {
            BotEngine::handleBotNightActions($room);
            self::saveRoom($room);
        } elseif ($phaseName === 'VOTING') {
            BotEngine::handleBotVoting($room);
            self::saveRoom($room);
        }
    }

    /**
     * Check if all living players are ready, and skip phase if so.
     */
    public static function checkAllReady(array &$room): void
    {
        $allReady = true;
        foreach ($room['players'] as $p) {
            if (($p['isAlive'] ?? true) && !($p['isReady'] ?? false)) {
                $allReady = false;
                break;
            }
        }

        if ($allReady) {
            self::advanceMatchLoop($room);
        }
    }

    /**
     * Checks if current phase duration has elapsed and advances game loop.
     */
    public static function checkAndAdvanceExpiredPhase(array &$room): void
    {
        $phase = $room['phase'] ?? 'LOBBY';
        $endsAt = $room['phaseEndsAt'] ?? 0;

        if ($phase === 'LOBBY' || $phase === 'GAME_OVER' || $endsAt <= 0) {
            return;
        }

        $now = (int)(microtime(true) * 1000);
        if ($now >= $endsAt) {
            self::advanceMatchLoop($room);
        }
    }

    /**
     * The Master Match Loop State Machine.
     */
    public static function advanceMatchLoop(array &$room): void
    {
        $phase = $room['phase'] ?? 'LOBBY';

        if ($phase === 'ROLE_REVEAL') {
            // Night 1 begins
            $room['round'] = ($room['round'] ?? 0) + 1;
            $room['nightActions'] = [];
            self::startPhase($room, 'NIGHT', $room['settings']['nightTimerSeconds'] ?? 60);

        } elseif ($phase === 'NIGHT') {
            // Resolve night actions
            $outcome = NightResolver::resolveNight($room);
            $room['lastNightOutcome'] = $outcome;
            self::startPhase($room, 'MORNING', 10);

        } elseif ($phase === 'MORNING') {
            // Post-night victory check
            $victory = VictoryEngine::evaluateVictory($room);
            if ($victory['isDecided'] ?? false) {
                self::finishGame($room, $victory);
                return;
            }

            // DAY phase (discussion & tasks)
            self::startPhase($room, 'DAY', $room['settings']['dayTimerSeconds'] ?? 120);

        } elseif ($phase === 'DAY') {
            // VOTING phase
            $room['votes'] = [];
            $room['lastEliminatedPlayer'] = null;
            $room['lastEliminatedCause'] = null;
            self::startPhase($room, 'VOTING', $room['settings']['votingTimerSeconds'] ?? 60);

        } elseif ($phase === 'VOTING') {
            // Tally votes and eliminate
            self::tallyVotesAndEliminate($room);

        } elseif ($phase === 'ELIMINATION') {
            // Post-elimination victory check
            $victory = VictoryEngine::evaluateVictory($room);
            if ($victory['isDecided'] ?? false) {
                self::finishGame($room, $victory);
                return;
            }

            // Next NIGHT
            $room['round'] = ($room['round'] ?? 0) + 1;
            $room['nightActions'] = [];
            self::startPhase($room, 'NIGHT', $room['settings']['nightTimerSeconds'] ?? 60);
        }
    }

    /**
     * Tallies votes and eliminates the candidate with maximum votes.
     */
    public static function tallyVotesAndEliminate(array &$room): void
    {
        $voteCounts = [];
        $players = &$room['players'];

        foreach ($room['votes'] ?? [] as $voterId => $targetId) {
            $voter = null;
            $target = null;
            foreach ($players as &$p) {
                if ($p['playerId'] === $voterId) $voter = &$p;
                if ($p['playerId'] === $targetId) $target = &$p;
            }
            if ($voter && ($voter['isAlive'] ?? true) && $target && ($target['isAlive'] ?? true)) {
                $voteCounts[$targetId] = ($voteCounts[$targetId] ?? 0) + 1;
            }
        }

        $maxVotes = 0;
        $candidates = [];
        foreach ($voteCounts as $targetId => $count) {
            if ($count > $maxVotes) {
                $maxVotes = $count;
                $candidates = [$targetId];
            } elseif ($count === $maxVotes) {
                $candidates[] = $targetId;
            }
        }

        if (count($candidates) === 1 && $maxVotes > 0) {
            $eliminated = null;
            foreach ($players as &$p) {
                if ($p['playerId'] === $candidates[0]) {
                    $p['isAlive'] = false;
                    $eliminated = $p;
                    break;
                }
            }
            $room['lastEliminatedPlayer'] = $eliminated;
            $room['lastEliminatedCause'] = 'VOTE';
        } else {
            $room['lastEliminatedPlayer'] = null;
            $room['lastEliminatedCause'] = 'TIE';
        }

        $victory = VictoryEngine::evaluateVictory($room);
        if ($victory['isDecided'] ?? false) {
            $room['victoryOutcome'] = $victory;
        }

        self::startPhase($room, 'ELIMINATION', 8);
    }

    /**
     * Finalize match into GAME_OVER and log to database for analytics.
     */
    public static function finishGame(array &$room, array $victory): void
    {
        $room['victoryOutcome'] = $victory;
        self::startPhase($room, 'GAME_OVER', 0);

        try {
            $duration = (int)((microtime(true) * 1000 - ($room['createdAt'] ?? microtime(true) * 1000)) / 1000);
            MatchLog::create([
                'room_code' => $room['roomCode'],
                'winning_faction' => $victory['faction'] ?? ($victory['kind'] ?? 'UNKNOWN'),
                'players_json' => $room['players'],
                'duration_seconds' => max(1, $duration),
            ]);
        } catch (\Throwable $e) {
            Log::error("[RoomManager] Failed to record MatchLog: " . $e->getMessage());
        }
    }

    /**
     * Sanitizes room state into pure JSON serializable array matching API specs.
     */
    public static function sanitizeRoomForJson(array $room): array
    {
        $cleanPlayers = array_map(function ($p) {
            $copy = $p;
            unset($copy['socketId'], $copy['reconnectTokenHash'], $copy['disconnectTimeout']);
            $copy['statuses'] = array_values($copy['statuses'] ?? []);
            return $copy;
        }, $room['players'] ?? []);

        return [
            'code'                 => $room['roomCode'] ?? $room['code'] ?? '',
            'roomCode'             => $room['roomCode'] ?? $room['code'] ?? '',
            'hostPlayerId'         => $room['hostPlayerId'] ?? '',
            'settings'             => $room['settings'] ?? [],
            'phase'                => $room['phase'] ?? 'LOBBY',
            'phaseStartedAt'       => $room['phaseStartedAt'] ?? 0,
            'phaseEndsAt'          => $room['phaseEndsAt'] ?? 0,
            'createdAt'            => $room['createdAt'] ?? 0,
            'lastActivityAt'       => $room['lastActivityAt'] ?? 0,
            'eventSequence'        => $room['eventSequence'] ?? 100,
            'round'                => $room['round'] ?? 0,
            'taskProgress'         => $room['taskProgress'] ?? 0,
            'darknessActive'       => $room['darknessActive'] ?? false,
            'vacantThrone'         => $room['vacantThrone'] ?? false,
            'lastEliminatedPlayer' => $room['lastEliminatedPlayer'] ?? null,
            'lastEliminatedCause'  => $room['lastEliminatedCause'] ?? null,
            'lastNightOutcome'     => $room['lastNightOutcome'] ?? null,
            'victoryOutcome'       => $room['victoryOutcome'] ?? null,
            'chatHistory'          => $room['chatHistory'] ?? [],
            'players'              => $cleanPlayers,
            'nightActions'         => (object)($room['nightActions'] ?? []),
            'votes'                => (object)($room['votes'] ?? []),
        ];
    }

    /**
     * Sanitizes room per specific player perspective (fog of war).
     */
    public static function sanitizeRoomForPlayer(array $room, string $playerId): array
    {
        $sanitized = self::sanitizeRoomForJson($room);
        $localPlayer = null;

        foreach ($sanitized['players'] as $p) {
            if ($p['playerId'] === $playerId) {
                $localPlayer = $p;
                break;
            }
        }

        $localFaction = $localPlayer['faction'] ?? 'kingdom';

        foreach ($sanitized['players'] as &$p) {
            if ($sanitized['phase'] !== 'GAME_OVER' && $p['playerId'] !== $playerId) {
                $isShadowAlly = ($localFaction === 'shadow' && ($p['faction'] ?? '') === 'shadow');
                if (!$isShadowAlly) {
                    unset($p['role'], $p['faction']);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Broadcast sanitized snapshot to room's Pusher channel.
     */
    public static function broadcastSnapshot(array $room): void
    {
        if (empty($room['roomCode'])) return;
        $snapshot = self::sanitizeRoomForJson($room);
        PusherService::broadcastToRoom($room['roomCode'], 's_room_snapshot', [
            'room' => $snapshot,
        ]);
    }

    /**
     * Get room from memory, cache, or database.
     */
    public static function getRoom(string $roomCode): ?array
    {
        $code = strtoupper(trim($roomCode));
        if (empty($code)) return null;

        // 1. In-memory
        if (isset(self::$inMemoryRooms[$code])) {
            self::checkAndAdvanceExpiredPhase(self::$inMemoryRooms[$code]);
            return self::$inMemoryRooms[$code];
        }

        // 2. Cache
        $cached = Cache::get("deceit_room_{$code}");
        if ($cached && is_array($cached)) {
            self::$inMemoryRooms[$code] = $cached;
            self::checkAndAdvanceExpiredPhase(self::$inMemoryRooms[$code]);
            return self::$inMemoryRooms[$code];
        }

        // 3. Database
        try {
            $row = DB::table('active_rooms')->where('room_code', $code)->first();
            if ($row) {
                $data = json_decode($row->room_data, true);
                if (is_array($data)) {
                    self::$inMemoryRooms[$code] = $data;
                    self::checkAndAdvanceExpiredPhase(self::$inMemoryRooms[$code]);
                    return self::$inMemoryRooms[$code];
                }
            }
        } catch (\Throwable $e) {
            // DB fallback
        }

        return null;
    }

    /**
     * Save room to in-memory, Cache, and active_rooms table.
     */
    public static function saveRoom(array $room): void
    {
        $code = strtoupper(trim($room['roomCode'] ?? $room['code'] ?? ''));
        if (empty($code)) return;

        self::$inMemoryRooms[$code] = $room;
        Cache::put("deceit_room_{$code}", $room, now()->addHours(6));

        try {
            DB::table('active_rooms')->updateOrInsert(
                ['room_code' => $code],
                [
                    'room_data' => json_encode($room, JSON_UNESCAPED_UNICODE),
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("[RoomManager] Could not save room {$code} to DB: " . $e->getMessage());
        }
    }

    /**
     * Delete room from in-memory, Cache, and database.
     */
    public static function deleteRoom(string $roomCode): void
    {
        $code = strtoupper(trim($roomCode));
        if (empty($code)) return;

        unset(self::$inMemoryRooms[$code]);
        Cache::forget("deceit_room_{$code}");

        try {
            DB::table('active_rooms')->where('room_code', $code)->delete();
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    /**
     * List all active rooms for Admin Dashboard.
     */
    public static function getAllActiveRooms(): array
    {
        $rooms = [];
        try {
            $rows = DB::table('active_rooms')->orderBy('updated_at', 'desc')->get();
            foreach ($rows as $r) {
                $room = self::getRoom($r->room_code);
                if ($room) {
                    $rooms[] = $room;
                }
            }
        } catch (\Throwable $e) {
            foreach (self::$inMemoryRooms as $r) {
                $rooms[] = $r;
            }
        }
        return $rooms;
    }
}
