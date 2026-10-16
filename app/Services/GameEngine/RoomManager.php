<?php

namespace App\Services\GameEngine;

use App\Models\MatchLog;
use App\Models\PlayerProfile;
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

        $liveTimers = GameConfigService::getTimers();
        $defaultSettings = [
            'maxPlayers'           => 16,
            'dayTimerSeconds'      => $liveTimers['dayTimerSeconds'],
            'nightTimerSeconds'    => $liveTimers['nightTimerSeconds'],
            'votingTimerSeconds'   => $liveTimers['votingTimerSeconds'],
            'roleRevealTimerSeconds'=> $liveTimers['roleRevealTimerSeconds'],
            'tieRule'              => GameConfigService::get('tie_rule', 'NO_ELIMINATION'),
            'kingMustSurvive'      => GameConfigService::get('king_must_survive', true),
            'revealEliminatedRole' => true,
            'enabledRoleIds'       => [],
        ];

        $now = (int)(microtime(true) * 1000);
        $clientIp = request()?->ip() ?? '127.0.0.1';

        $hostPlayer = [
            'playerId'           => $hostPlayerId,
            'socketId'           => null,
            'nickname'           => trim($hostNickname) ?: 'Host',
            'isHost'             => true,
            'isBot'              => false,
            'isAlive'            => true,
            'isConnected'        => true,
            'isReady'            => true,
            'ip'                 => $clientIp,
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

        $clientIp = request()?->ip() ?? '127.0.0.1';

        $player = [
            'playerId'           => $playerId,
            'socketId'           => null,
            'nickname'           => $cleanName,
            'isHost'             => false,
            'isBot'              => false,
            'isAlive'            => true,
            'isConnected'        => true,
            'isReady'            => false,
            'ip'                 => $clientIp,
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

        if (($room['phase'] ?? 'LOBBY') !== 'LOBBY') {
            // If in-game, mark player dead and disconnected so game balance and turns continue properly
            $room['players'][$playerIndex]['isAlive'] = false;
            $room['players'][$playerIndex]['isConnected'] = false;

            // Reassign host if leaving player was host
            if ($leavingPlayer['isHost'] ?? false) {
                $room['players'][$playerIndex]['isHost'] = false;
                foreach ($room['players'] as &$p) {
                    if (!($p['isBot'] ?? false) && ($p['isAlive'] ?? false)) {
                        $p['isHost'] = true;
                        $room['hostPlayerId'] = $p['playerId'];
                        break;
                    }
                }
            }

            // Check if any connected human players remain
            $humanCount = count(array_filter($room['players'], fn($p) => !($p['isBot'] ?? false) && ($p['isConnected'] ?? true)));
            if ($humanCount === 0) {
                self::deleteRoom($code);
                PusherService::broadcastToRoom($code, 's_room_destroyed', [
                    'reason' => 'No human players remaining.'
                ]);
                return ['roomDestroyed' => true];
            }

            // Check victory condition in case leaver altered win condition
            $victory = VictoryEngine::evaluateVictory($room);
            if ($victory['isDecided'] ?? false) {
                self::finishGame($room, $victory);
                return ['room' => $room, 'roomDestroyed' => false];
            }

            // Check if all remaining living players are ready
            self::checkAllReady($room);
        } else {
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
        }

        $room['lastActivityAt'] = (int)(microtime(true) * 1000);
        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return ['room' => $room, 'roomDestroyed' => false];
    }

    /**
     * Reconnect player to an existing room using reconnectToken.
     */
    public static function reconnectPlayer(string $roomCode, string $reconnectToken): array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);

        if (!$room) return ['error' => 'ROOM_NOT_FOUND', 'message' => 'الغرفة غير موجودة أو انتهت صلاحيتها.'];

        $tokenHash = hash('sha256', $reconnectToken);
        $foundIndex = -1;

        foreach ($room['players'] as $idx => $p) {
            if (($p['reconnectTokenHash'] ?? '') === $tokenHash) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === -1) {
            return ['error' => 'INVALID_TOKEN', 'message' => 'رمز إعادة الاتصال غير صالح.'];
        }

        $room['players'][$foundIndex]['isConnected'] = true;
        $room['lastActivityAt'] = (int)(microtime(true) * 1000);

        self::saveRoom($room);
        self::broadcastSnapshot($room);

        return [
            'success' => true,
            'roomCode' => $code,
            'playerId' => $room['players'][$foundIndex]['playerId'],
            'player' => $room['players'][$foundIndex],
            'reconnectToken' => $reconnectToken,
            'room' => self::sanitizeRoomForJson($room),
        ];
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
        \App\Services\Moderation\AntiCheatEngine::detectTeaming($room);
        $roleRevealTimer = (int)($room['settings']['roleRevealTimerSeconds'] ?? 8);
        self::startPhase($room, 'ROLE_REVEAL', $roleRevealTimer);

        return ['success' => true, 'room' => $room];
    }

    /**
     * Submit a night action with atomic concurrency locking.
     */
    public static function submitNightAction(string $roomCode, string $playerId, string $targetPlayerId, array $targetPlayerIds = []): array
    {
        $code = strtoupper(trim($roomCode));
        try {
            $lock = Cache::lock("room_lock_{$code}", 5);
            return $lock->block(2, function () use ($code, $playerId, $targetPlayerId, $targetPlayerIds) {
                $room = self::getRoom($code);

                if (!$room) return ['error' => 'ROOM_NOT_FOUND', 'message' => 'الغرفة غير موجودة.'];

                // 1. Phase validation
                if (($room['phase'] ?? '') !== 'NIGHT') {
                    return ['error' => 'INVALID_PHASE', 'message' => 'لا يمكن تقديم الإجراءات إلا في طور الليل.'];
                }

                $player = null;
                $playerMap = [];
                foreach ($room['players'] as &$p) {
                    $playerMap[$p['playerId']] = $p;
                    if ($p['playerId'] === $playerId) {
                        $player = &$p;
                    }
                }

                // 2. Player validation
                if (!$player || !($player['isAlive'] ?? true)) {
                    return ['error' => 'INVALID_PLAYER', 'message' => 'اللاعب غير صالح أو مستبعد.'];
                }

                $roleId = $player['role'] ?? '';

                // 3. Role capability validation
                if (!RoleData::hasNightAction($roleId)) {
                    return ['error' => 'ROLE_HAS_NO_ACTION', 'message' => 'شخصيتك لا تملك قدرة ليلية نشطة.'];
                }

                $ids = !empty($targetPlayerIds) ? $targetPlayerIds : (!empty($targetPlayerId) ? [$targetPlayerId] : []);
                $requiredTargets = RoleData::getRequiredTargets($roleId);

                // 4. Target count validation
                if (count($ids) < $requiredTargets) {
                    return [
                        'error' => 'INSUFFICIENT_TARGETS',
                        'message' => "يجب تحديد {$requiredTargets} أهداف لاستخدام هذه القدرة."
                    ];
                }

                // 5. Target existence and alive validation
                foreach ($ids as $tid) {
                    if (!isset($playerMap[$tid])) {
                        return ['error' => 'TARGET_NOT_FOUND', 'message' => 'أحد الأهداف المحددة غير موجود في الغرفة.'];
                    }
                    if (!($playerMap[$tid]['isAlive'] ?? true)) {
                        return ['error' => 'TARGET_NOT_ALIVE', 'message' => 'لا يمكن استهداف لاعب مستبعد.'];
                    }
                }

                // 6. Self-targeting validation
                if (in_array($playerId, $ids, true) && !RoleData::canTargetSelf($roleId)) {
                    return ['error' => 'CANNOT_TARGET_SELF', 'message' => 'لا يمكنك استهداف نفسك بهذه القدرة.'];
                }

                // 7. Ally-targeting validation (for Shadow faction)
                $actorFaction = $player['faction'] ?? RoleData::getFaction($roleId);
                if ($actorFaction === 'shadow' && !RoleData::canTargetAllies($roleId)) {
                    foreach ($ids as $tid) {
                        $targetFaction = $playerMap[$tid]['faction'] ?? RoleData::getFaction($playerMap[$tid]['role'] ?? '');
                        if ($targetFaction === 'shadow') {
                            return ['error' => 'CANNOT_TARGET_ALLY', 'message' => 'لا يمكنك استهداف أحد رفاقك في الظلال.'];
                        }
                    }
                }

                // 8. Saboteur check: target must have an active night action
                if ($roleId === 'saboteur') {
                    $targetRole = $playerMap[$ids[0]]['role'] ?? '';
                    if (!RoleData::hasNightAction($targetRole)) {
                        return ['error' => 'TARGET_HAS_NO_ACTION', 'message' => 'الهدف المختار لا يملك قدرة ليلية نشطة لتعطيلها.'];
                    }
                }

                // 9. Repeat target validation (noRepeatTarget)
                if (RoleData::noRepeatTarget($roleId)) {
                    $prevTargets = $room['lastNightTargets'][$playerId] ?? [];
                    if (!empty(array_intersect($ids, $prevTargets))) {
                        return ['error' => 'CANNOT_REPEAT_TARGET', 'message' => 'لا يمكنك استهداف نفس اللاعب ليلتين متتاليتين.'];
                    }
                }

                // 10. Record night action
                $room['nightActions'][$playerId] = [
                    'actorPlayerId'   => $playerId,
                    'roleId'          => $roleId,
                    'targetPlayerId'  => $ids[0] ?? '',
                    'targetPlayerIds' => $ids,
                    'submittedAt'     => (int)(microtime(true) * 1000),
                ];

                // 11. If Shadow Leader, update recommendation and broadcast live to Shadow faction
                if ($roleId === 'shadow_leader') {
                    $room['shadowRecommendation'] = $ids[0];
                    PusherService::broadcastToRoom($room['roomCode'], 's_shadow_recommendation', [
                        'recommendationTargetId' => $ids[0],
                        'recommendedNickname' => $playerMap[$ids[0]]['nickname'] ?? '',
                    ]);
                }

                $player['isReady'] = true;
                self::saveRoom($room);
                self::broadcastSnapshot($room);
                self::checkAllReady($room);

                return ['success' => true, 'room' => $room];
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return ['error' => 'CONCURRENCY_LOCK_TIMEOUT'];
        }
    }

    /**
     * Cast vote during VOTING phase with atomic concurrency locking.
     */
    public static function castVote(string $roomCode, string $playerId, ?string $targetPlayerId): array
    {
        $code = strtoupper(trim($roomCode));
        try {
            $lock = Cache::lock("room_lock_{$code}", 5);
            return $lock->block(2, function () use ($code, $playerId, $targetPlayerId) {
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

                return ['success' => true, 'voteTally' => $voteTally, 'room' => $room];
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return ['error' => 'CONCURRENCY_LOCK_TIMEOUT'];
        }
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
            $room['dayIntel'] = [];
            self::startPhase($room, 'NIGHT', $room['settings']['nightTimerSeconds'] ?? 60);

        } elseif ($phase === 'NIGHT') {
            // Save last night targets for repeat check
            $lastTargets = [];
            foreach ($room['nightActions'] ?? [] as $actorId => $act) {
                $lastTargets[$actorId] = !empty($act['targetPlayerIds']) ? $act['targetPlayerIds'] : (!empty($act['targetPlayerId']) ? [$act['targetPlayerId']] : []);
            }
            $room['lastNightTargets'] = $lastTargets;

            // Resolve night actions
            $outcome = NightResolver::resolveNight($room);
            $room['lastNightOutcome'] = $outcome;
            $room['shadowRecommendation'] = null; // Clear recommendation after night ends
            $room['dayIntel'] = $outcome['intel'] ?? [];
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
            $room['dayIntel'] = [];
            self::startPhase($room, 'NIGHT', $room['settings']['nightTimerSeconds'] ?? 60);
        }
    }

    /**
     * Tallies votes and eliminates the candidate with maximum votes.
     */
    public static function tallyVotesAndEliminate(array &$room): void
    {
        $voteCounts = [];
        $playerMap = [];
        foreach ($room['players'] as $p) {
            $playerMap[$p['playerId']] = $p;
        }

        foreach ($room['votes'] ?? [] as $voterId => $targetId) {
            $voter = $playerMap[$voterId] ?? null;
            $target = $playerMap[$targetId] ?? null;
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
            foreach ($room['players'] as $idx => $p) {
                if ($p['playerId'] === $candidates[0]) {
                    $room['players'][$idx]['isAlive'] = false;
                    $eliminated = $room['players'][$idx];
                    break;
                }
            }
            $room['lastEliminatedPlayer'] = $eliminated;
            $room['lastEliminatedCause'] = 'VOTE';

            // Daytime Crown Prince succession check
            if (($eliminated['role'] ?? '') === 'king') {
                $crownPrinceId = null;
                foreach ($room['players'] as $idx => $p) {
                    if (($p['role'] ?? '') === 'crown_prince' && ($p['isAlive'] ?? true)) {
                        $room['players'][$idx]['role'] = 'king';
                        $crownPrinceId = $p['playerId'];
                        break;
                    }
                }
                if ($crownPrinceId) {
                    $room['vacantThrone'] = false;
                    if (!isset($room['dayIntel']) || !is_array($room['dayIntel'])) {
                        $room['dayIntel'] = [];
                    }
                    $room['dayIntel'][$crownPrinceId] = [
                        'type' => 'CROWN_PRINCE_SUCCESSION',
                        'playerId' => $crownPrinceId,
                        'text' => 'استشهد الملك في التصويت! لقد توليت منصب العرش لحماية المملكة.',
                    ];
                    PusherService::broadcastToRoom($room['roomCode'], 's_crown_prince_succession', [
                        'newKingPlayerId' => $crownPrinceId,
                        'message' => 'استشهد الملك في التصويت النهاري! تولى ولي العهد منصب العرش لحماية المملكة.'
                    ]);
                } else {
                    $room['vacantThrone'] = true;
                }
            }
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
     * Executes Judge's Veto ability to cancel voting elimination.
     */
    public static function applyVeto(string $roomCode, string $playerId): array
    {
        $code = strtoupper(trim($roomCode));
        try {
            $lock = Cache::lock("room_lock_{$code}", 5);
            return $lock->block(2, function () use ($code, $playerId) {
                $room = self::getRoom($code);
                if (!$room) return ['error' => 'ROOM_NOT_FOUND', 'message' => 'الغرفة غير موجودة.'];

                // 1. Verify player is judge and alive
                $player = null;
                foreach ($room['players'] as &$p) {
                    if ($p['playerId'] === $playerId) {
                        $player = &$p;
                        break;
                    }
                }
                if (!$player || !($player['isAlive'] ?? true)) {
                    return ['error' => 'INVALID_PLAYER', 'message' => 'اللاعب غير صالح أو مستبعد.'];
                }
                if (($player['role'] ?? '') !== 'judge') {
                    return ['error' => 'NOT_JUDGE', 'message' => 'هذه القدرة خاصة بالقاضي فقط.'];
                }

                // 2. Verify phase is VOTING or ELIMINATION
                $phase = $room['phase'] ?? '';
                if ($phase !== 'VOTING' && $phase !== 'ELIMINATION') {
                    return ['error' => 'INVALID_PHASE', 'message' => 'لا يمكن استخدام حق الاعتراض إلا أثناء أو فور انتهاء التصويت.'];
                }

                // 3. Verify cooldown and uses
                $state = $room['judgeVetoState'] ?? [
                    'usesRemaining' => 7,
                    'lastVetoRound' => -99,
                ];
                $currentRound = (int)($room['round'] ?? 1);

                if (($state['usesRemaining'] ?? 0) <= 0) {
                    return ['error' => 'NO_USES_REMAINING', 'message' => 'لقد استنفدت كامل مرات الاعتراض المتاحة.'];
                }

                $lastVeto = (int)($state['lastVetoRound'] ?? -99);
                if (($currentRound - $lastVeto) < 3) {
                    $daysLeft = 3 - ($currentRound - $lastVeto);
                    return ['error' => 'COOLDOWN_ACTIVE', 'message' => "يجب الانتظار {$daysLeft} أيام قبل استخدام حق الاعتراض مجدداً."];
                }

                // 4. Cancel elimination
                $eliminatedPlayer = $room['lastEliminatedPlayer'] ?? null;
                if ($eliminatedPlayer) {
                    // Revive player
                    foreach ($room['players'] as &$p) {
                        if ($p['playerId'] === $eliminatedPlayer['playerId']) {
                            $p['isAlive'] = true;
                            break;
                        }
                    }
                }

                $room['lastEliminatedPlayer'] = null;
                $room['lastEliminatedCause'] = 'VETO';
                $room['victoryOutcome'] = null;

                // Update judge state
                $state['usesRemaining']--;
                $state['lastVetoRound'] = $currentRound;
                $room['judgeVetoState'] = $state;

                self::saveRoom($room);

                // Broadcast veto event to room
                PusherService::broadcastToRoom($room['roomCode'], 's_judge_veto', [
                    'message' => 'تدخل القاضي بحق الاعتراض (Veto)! أُلغيت نتيجة التصويت ولم يُستبعد أحد اليوم.',
                    'room' => self::sanitizeRoomForJson($room),
                ]);
                self::broadcastSnapshot($room);

                return [
                    'success' => true,
                    'message' => 'تم استخدام حق الاعتراض وإلغاء نتيجة التصويت بنجاح.',
                    'room' => self::sanitizeRoomForJson($room),
                ];
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return ['error' => 'CONCURRENCY_LOCK_TIMEOUT'];
        }
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

            // Award coins based on live configuration
            $winReward = (int)GameConfigService::get('win_coin_reward', 50);
            $lossReward = (int)GameConfigService::get('loss_coin_reward', 10);
            $winningFaction = strtolower($victory['faction'] ?? ($victory['kind'] ?? ''));

            foreach ($room['players'] as $player) {
                if ($player['isBot'] ?? false) continue;
                $profile = PlayerProfile::where('player_id', $player['playerId'])
                    ->orWhere('nickname', $player['nickname'])
                    ->first();
                if ($profile) {
                    $isWinner = !empty($player['faction']) && str_contains($winningFaction, strtolower($player['faction']));
                    $coinsToAdd = $isWinner ? $winReward : $lossReward;
                    $profile->increment('coins', $coinsToAdd);
                    $profile->increment('matches_played');
                    if ($isWinner) {
                        $profile->increment('matches_won');
                    }
                }
            }
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

        $judgeState = $room['judgeVetoState'] ?? [
            'usesRemaining' => 7,
            'lastVetoRound' => -99,
        ];
        $currentRound = (int)($room['round'] ?? 1);
        $lastVeto = (int)($judgeState['lastVetoRound'] ?? -99);
        $usesRemaining = (int)($judgeState['usesRemaining'] ?? 7);
        $cooldownRemaining = max(0, 3 - ($currentRound - $lastVeto));
        $canVeto = ($usesRemaining > 0) && ($cooldownRemaining === 0);

        $computedJudgeVetoState = [
            'canVeto' => $canVeto,
            'usesRemaining' => $usesRemaining,
            'cooldownRemaining' => $cooldownRemaining,
            'lastVetoRound' => $lastVeto,
        ];

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
            'shadowRecommendation' => $room['shadowRecommendation'] ?? null,
            'dayIntel'             => array_values($room['dayIntel'] ?? []),
            'judgeVetoState'        => $computedJudgeVetoState,
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

        // Hide shadow recommendation from non-shadow players
        if ($localFaction !== 'shadow') {
            unset($sanitized['shadowRecommendation']);
        }

        return $sanitized;
    }

    /**
     * Broadcast sanitized snapshot to room's Pusher channel and God-Mode spectator channel.
     */
    public static function broadcastSnapshot(array $room): void
    {
        if (empty($room['roomCode'])) return;
        $snapshot = self::sanitizeRoomForJson($room);
        PusherService::broadcastToRoom($room['roomCode'], 's_room_snapshot', [
            'room' => $snapshot,
        ]);

        // Broadcast unredacted God-Mode snapshot to admin monitor channel if actively spectated or testing
        try {
            if (PusherService::$fake || Cache::has("admin_spectator_active_{$room['roomCode']}")) {
                $spectator = self::getSpectatorSnapshot($room['roomCode']);
                if ($spectator) {
                    PusherService::trigger("admin-spectator-{$room['roomCode']}", 's_spectator_update', [
                        'snapshot' => $spectator,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore broadcast failure on admin channel if not listening
        }
    }

    /**
     * Build God-Mode unredacted spectator snapshot with vote heatmap and action targets.
     */
    public static function getSpectatorSnapshot(string $roomCode): ?array
    {
        $code = strtoupper(trim($roomCode));
        $room = self::getRoom($code);
        if (!$room) return null;

        $now = (int)(microtime(true) * 1000);
        $phaseEndsAt = $room['phaseEndsAt'] ?? 0;
        $timeLeft = max(0, (int)ceil(($phaseEndsAt - $now) / 1000));

        // Compute Vote Heatmap (who voted for whom and total count)
        $voteHeatmap = [];
        $votes = $room['votes'] ?? [];
        foreach ($votes as $voterId => $targetId) {
            if ($targetId) {
                $voteHeatmap[$targetId] = ($voteHeatmap[$targetId] ?? 0) + 1;
            }
        }

        // Night Action Targets mapping
        $nightActions = $room['nightActions'] ?? [];

        // Players dictionary for quick name lookups
        $playerNames = [];
        foreach ($room['players'] as $p) {
            $playerNames[$p['playerId']] = $p['nickname'] ?? $p['playerId'];
        }

        $augmentedPlayers = array_map(function ($p) use ($nightActions, $votes, $voteHeatmap, $playerNames) {
            $pid = $p['playerId'];
            $nightTargetId = $nightActions[$pid]['targetPlayerId'] ?? ($p['nightAction']['targetPlayerId'] ?? null);
            $voteTargetId = $votes[$pid] ?? ($p['vote'] ?? null);

            return [
                'playerId'        => $pid,
                'nickname'        => $p['nickname'] ?? '',
                'role'            => $p['role'] ?? 'لم يحدد',
                'faction'         => $p['faction'] ?? 'kingdom',
                'isAlive'         => (bool)($p['isAlive'] ?? true),
                'isConnected'     => (bool)($p['isConnected'] ?? true),
                'isBot'           => (bool)($p['isBot'] ?? false),
                'isHost'          => (bool)($p['isHost'] ?? false),
                'ip'              => $p['ip'] ?? '127.0.0.1',
                'statuses'        => array_values($p['statuses'] ?? []),
                'nightTargetId'   => $nightTargetId,
                'nightTargetName' => $nightTargetId ? ($playerNames[$nightTargetId] ?? $nightTargetId) : null,
                'voteTargetId'    => $voteTargetId,
                'voteTargetName'  => $voteTargetId ? ($playerNames[$voteTargetId] ?? $voteTargetId) : null,
                'incomingVotes'   => $voteHeatmap[$pid] ?? 0,
            ];
        }, $room['players'] ?? []);

        return [
            'roomCode'             => $code,
            'phase'                => $room['phase'] ?? 'LOBBY',
            'round'                => $room['round'] ?? 0,
            'timeLeft'             => $timeLeft,
            'taskProgress'         => $room['taskProgress'] ?? 0,
            'darknessActive'       => $room['darknessActive'] ?? false,
            'lastNightOutcome'     => $room['lastNightOutcome'] ?? null,
            'lastEliminatedPlayer' => $room['lastEliminatedPlayer'] ?? null,
            'lastEliminatedCause'  => $room['lastEliminatedCause'] ?? null,
            'victoryOutcome'       => $room['victoryOutcome'] ?? null,
            'chatHistory'          => $room['chatHistory'] ?? [],
            'players'              => $augmentedPlayers,
            'voteHeatmap'          => $voteHeatmap,
            'totalPlayers'         => count($augmentedPlayers),
            'alivePlayers'         => count(array_filter($augmentedPlayers, fn($p) => $p['isAlive'])),
        ];
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
            Cache::lock("room_lock_{$code}")->forceRelease();
        } catch (\Throwable $e) {
            // Ignore
        }

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

    /**
     * Quick match: find an open room in LOBBY phase that is not full, or create a new one.
     */
    public static function quickMatch(string $nickname, ?array $customSettings = null): array
    {
        $activeRooms = self::getAllActiveRooms();
        $cleanName = trim($nickname) ?: ('لاعب_' . rand(1000, 9999));

        foreach ($activeRooms as $room) {
            if (($room['phase'] ?? '') !== 'LOBBY') {
                continue;
            }
            $maxPlayers = $room['settings']['maxPlayers'] ?? 16;
            $currentPlayers = count($room['players'] ?? []);
            if ($currentPlayers < $maxPlayers) {
                $joinResult = self::joinRoom($room['roomCode'], $cleanName);
                if (!isset($joinResult['error'])) {
                    $joinResult['isNewRoom'] = false;
                    return $joinResult;
                }
            }
        }

        // No open room found, create a new one
        $createResult = self::createRoom($cleanName, $customSettings ?? []);
        $createResult['isNewRoom'] = true;
        $createResult['player'] = $createResult['hostPlayer'];
        return $createResult;
    }
}
