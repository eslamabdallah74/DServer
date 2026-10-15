<?php

namespace App\Services\Moderation;

use App\Models\SuspiciousActivity;
use Illuminate\Support\Facades\Log;

class AntiCheatEngine
{
    /**
     * Toxicity regex keywords (common Arabic curses, slurs, explicit toxicity).
     */
    private const TOXIC_PATTERNS = [
        '/\b(كلب|حمار|حيوان|قذر|شرموط|عاهر|ابن الكلب|يلعن|منيوك|كس|طيز|عرص)\b/iu',
        '/\b(fuck|bitch|bastard|asshole|whore)\b/iu',
    ];

    /**
     * Role leak patterns.
     */
    private const ROLE_LEAK_PATTERNS = [
        '/(أنا|انا)\s+(القاتل|السفاح|المخرب|المسمم|الساحر)/iu',
        '/(هو|هي)\s+(القاتل|السفاح|المخرب|المسمم|الساحر)/iu',
    ];

    /**
     * Analyze room for teaming collusion and IP clustering across opposing factions.
     *
     * @param array $room
     * @param bool $persistToDb Whether to save detections into suspicious_activities table
     * @return array List of detected suspicious pairs
     */
    public static function detectTeaming(array $room, bool $persistToDb = true): array
    {
        $players = $room['players'] ?? [];
        $roomCode = $room['roomCode'] ?? ($room['code'] ?? 'UNKNOWN');
        $suspicious = [];

        // Filter out bots and players without IP or role
        $humans = array_filter($players, function ($p) {
            return !($p['isBot'] ?? false) && !empty($p['ip']) && !empty($p['role']);
        });

        $byIp = [];
        foreach ($humans as $player) {
            $ip = $player['ip'];
            $byIp[$ip][] = $player;
        }

        foreach ($byIp as $ip => $group) {
            if (count($group) < 2) {
                continue;
            }

            // Compare each pair in group
            $count = count($group);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $p1 = $group[$i];
                    $p2 = $group[$j];

                    $f1 = strtolower($p1['faction'] ?? '');
                    $f2 = strtolower($p2['faction'] ?? '');

                    // Flag if opposing factions on same IP
                    $isOpposing = ($f1 === 'kingdom' && $f2 === 'shadow') ||
                                  ($f1 === 'shadow' && $f2 === 'kingdom');

                    if ($isOpposing) {
                        $reason = 'SAME_IP_OPPOSING_TEAMS';
                        $severity = 'critical';
                    } else {
                        $reason = 'SAME_IP_MULTI_ACCOUNT';
                        $severity = 'warning';
                    }

                    $entry = [
                        'roomCode' => $roomCode,
                        'playerA' => $p1['playerId'],
                        'playerAName' => $p1['nickname'] ?? '',
                        'playerARole' => $p1['role'] ?? '',
                        'playerB' => $p2['playerId'],
                        'playerBName' => $p2['nickname'] ?? '',
                        'playerBRole' => $p2['role'] ?? '',
                        'reason' => $reason,
                        'severity' => $severity,
                        'ip' => $ip,
                    ];

                    $suspicious[] = $entry;

                    if ($persistToDb) {
                        try {
                            SuspiciousActivity::firstOrCreate(
                                [
                                    'room_code' => $roomCode,
                                    'player_a_id' => $p1['playerId'],
                                    'player_b_id' => $p2['playerId'],
                                    'reason' => $reason,
                                ],
                                [
                                    'severity' => $severity,
                                    'ip_address' => $ip,
                                    'details' => $entry,
                                    'status' => 'pending',
                                ]
                            );
                        } catch (\Throwable $e) {
                            Log::warning("[AntiCheatEngine] DB save error: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        return $suspicious;
    }

    /**
     * Check chat message for toxic language or unauthorized role leaks.
     */
    public static function checkChatToxicity(string $text, string $playerId, ?string $roomCode = null, bool $persistToDb = true): array
    {
        $cleanText = trim($text);

        // Check for toxicity
        foreach (self::TOXIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $cleanText)) {
                $reason = 'CHAT_TOXICITY';
                if ($persistToDb) {
                    self::recordActivity($roomCode, $playerId, null, $reason, 'warning', [
                        'text' => $cleanText,
                        'pattern' => $pattern,
                    ]);
                }
                return ['toxic' => true, 'reason' => $reason];
            }
        }

        // Check for role leak
        foreach (self::ROLE_LEAK_PATTERNS as $pattern) {
            if (preg_match($pattern, $cleanText)) {
                $reason = 'ROLE_LEAK';
                if ($persistToDb) {
                    self::recordActivity($roomCode, $playerId, null, $reason, 'critical', [
                        'text' => $cleanText,
                        'pattern' => $pattern,
                    ]);
                }
                return ['toxic' => true, 'reason' => $reason];
            }
        }

        return ['toxic' => false, 'reason' => null];
    }

    private static function recordActivity(?string $roomCode, string $playerA, ?string $playerB, string $reason, string $severity, array $details): void
    {
        try {
            SuspiciousActivity::create([
                'room_code' => $roomCode ?? 'CHAT',
                'player_a_id' => $playerA,
                'player_b_id' => $playerB,
                'reason' => $reason,
                'severity' => $severity,
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'details' => $details,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning("[AntiCheatEngine] Activity record error: " . $e->getMessage());
        }
    }
}

