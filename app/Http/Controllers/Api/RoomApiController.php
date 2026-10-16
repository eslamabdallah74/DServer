<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameEngine\ChatEngine;
use App\Services\GameEngine\RoomManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomApiController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        try {
            $nickname = $request->input('nickname', 'Host');
            $settings = $request->input('settings', []);

            $result = RoomManager::createRoom($nickname, is_array($settings) ? $settings : []);

            return response()->json([
                'success' => true,
                'roomCode' => $result['room']['roomCode'],
                'playerId' => $result['hostPlayer']['playerId'],
                'player' => $result['hostPlayer'],
                'hostPlayer' => $result['hostPlayer'],
                'reconnectToken' => $result['reconnectToken'],
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function join(Request $request): JsonResponse
    {
        try {
            $roomCode = $request->input('roomCode');
            $nickname = $request->input('nickname');

            if (empty($roomCode)) {
                return response()->json(['success' => false, 'error' => 'ROOM_CODE_REQUIRED'], 400);
            }

            $result = RoomManager::joinRoom($roomCode, $nickname ?: '');
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json([
                'success' => true,
                'roomCode' => $result['room']['roomCode'],
                'playerId' => $result['player']['playerId'],
                'player' => $result['player'],
                'reconnectToken' => $result['reconnectToken'],
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function start(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $result = RoomManager::startGame($roomCode, $playerId ?: '');

            if (isset($result['error'])) {
                $status = ($result['error'] === 'NOT_HOST') ? 403 : (($result['error'] === 'ROOM_NOT_FOUND') ? 404 : 400);
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'] ?? $result['error'],
                ], $status);
            }

            return response()->json([
                'success' => true,
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function ready(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $nickname = $request->input('nickname');
            $isReady = $request->has('isReady') ? $request->boolean('isReady') : null;

            $result = RoomManager::toggleReady($roomCode, $playerId ?: '', $isReady, $nickname);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json([
                'success' => true,
                'isReady' => $result['isReady'],
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function action(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $targetPlayerId = $request->input('targetPlayerId', '');
            $targetPlayerIds = $request->input('targetPlayerIds', $request->input('targetIds', []));

            $result = RoomManager::submitNightAction($roomCode, $playerId ?: '', $targetPlayerId ?: '', is_array($targetPlayerIds) ? $targetPlayerIds : []);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function vote(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $targetPlayerId = $request->input('targetPlayerId', $request->input('targetId'));

            $result = RoomManager::castVote($roomCode, $playerId ?: '', $targetPlayerId);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function addBot(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId', $request->input('hostPlayerId'));
            $room = RoomManager::getRoom($roomCode);

            if (!$room) return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);
            if (($room['hostPlayerId'] ?? '') !== $playerId) return response()->json(['success' => false, 'error' => 'NOT_HOST'], 403);

            $result = RoomManager::addBot($roomCode);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json([
                'success' => true,
                'bot' => $result['bot'] ?? null,
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function removeBot(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId', $request->input('hostPlayerId'));
            $botPlayerId = $request->input('botPlayerId', $request->input('targetPlayerId'));
            $room = RoomManager::getRoom($roomCode);

            if (!$room) return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);
            if (($room['hostPlayerId'] ?? '') !== $playerId) return response()->json(['success' => false, 'error' => 'NOT_HOST'], 403);

            $result = RoomManager::removeBot($roomCode, $botPlayerId);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json([
                'success' => true,
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function kickPlayer(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId', $request->input('hostPlayerId'));
            $targetPlayerId = $request->input('targetPlayerId');

            $result = RoomManager::kickPlayer($roomCode, $playerId ?: '', $targetPlayerId ?: '');
            if (isset($result['error'])) {
                $status = ($result['error'] === 'NOT_HOST') ? 403 : (($result['error'] === 'ROOM_NOT_FOUND' || $result['error'] === 'PLAYER_NOT_FOUND') ? 404 : 400);
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'] ?? $result['error']
                ], $status);
            }

            return response()->json([
                'success' => true,
                'room' => RoomManager::sanitizeRoomForJson($result['room']),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function chat(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $text = $request->input('text', $request->input('message', ''));

            $room = RoomManager::getRoom($roomCode);
            if (!$room) return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);

            $player = null;
            foreach ($room['players'] as $p) {
                if ($p['playerId'] === $playerId) {
                    $player = $p;
                    break;
                }
            }

            if (!$player) {
                return response()->json(['success' => false, 'error' => 'PLAYER_NOT_FOUND'], 400);
            }

            $result = ChatEngine::processMessage($room, $player, $text);
            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            RoomManager::saveRoom($room);
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'chatMessage' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function leave(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $result = RoomManager::leaveRoom($roomCode, $playerId ?: '');

            if (isset($result['error'])) {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function settings(Request $request, string $roomCode): JsonResponse
    {
        try {
            $playerId = $request->input('playerId');
            $newSettings = $request->input('settings', []);

            $room = RoomManager::getRoom($roomCode);
            if (!$room) return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);
            if (($room['hostPlayerId'] ?? '') !== $playerId) return response()->json(['success' => false, 'error' => 'NOT_HOST'], 403);

            if (is_array($newSettings)) {
                $room['settings'] = array_merge($room['settings'] ?? [], $newSettings);
                RoomManager::saveRoom($room);
                RoomManager::broadcastSnapshot($room);
            }

            return response()->json([
                'success' => true,
                'room' => RoomManager::sanitizeRoomForJson($room),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function veto(Request $request, string $roomCode): JsonResponse
    {
        $playerId = $request->input('playerId') ?? $request->header('X-Player-Id');
        if (empty($playerId)) {
            return response()->json(['success' => false, 'error' => 'PLAYER_ID_REQUIRED', 'message' => 'معرف اللاعب مطلوب لاستخدام حق الفيتو.'], 400);
        }

        $result = RoomManager::applyVeto($roomCode, $playerId);
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'message' => $result['message'] ?? '',
            ], 400);
        }

        return response()->json($result);
    }

    public function show(string $roomCode): JsonResponse
    {
        $room = RoomManager::getRoom($roomCode);
        if (!$room) return response()->json(['success' => false, 'error' => 'ROOM_NOT_FOUND'], 404);

        return response()->json([
            'success' => true,
            'room' => RoomManager::sanitizeRoomForJson($room),
        ]);
    }

    public function reconnect(Request $request, string $roomCode): JsonResponse
    {
        try {
            $reconnectToken = $request->input('reconnectToken', '');
            if (empty($reconnectToken)) {
                return response()->json(['success' => false, 'error' => 'TOKEN_REQUIRED', 'message' => 'رمز إعادة الاتصال مطلوب.'], 400);
            }

            $result = RoomManager::reconnectPlayer($roomCode, $reconnectToken);
            if (isset($result['error'])) {
                $status = ($result['error'] === 'ROOM_NOT_FOUND') ? 404 : 400;
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'] ?? $result['error'],
                ], $status);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function sync(string $roomCode): JsonResponse
    {
        return $this->show($roomCode);
    }
}
