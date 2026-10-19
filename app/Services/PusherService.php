<?php

namespace App\Services;

use App\Services\GameEngine\RoomManager;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PusherService
{
    private static ?Client $httpClient = null;
    public static bool $fake = false;
    public static array $dispatchedEvents = [];

    /**
     * Fake pusher for testing purposes.
     */
    public static function fake(): void
    {
        self::$fake = true;
        self::$dispatchedEvents = [];
    }

    public static function resetFake(): void
    {
        self::$fake = false;
        self::$dispatchedEvents = [];
    }

    /**
     * Test helper assertion for event dispatch.
     */
    public static function assertBroadcast(string $channel, string $eventName): void
    {
        $found = false;
        foreach (self::$dispatchedEvents as $evt) {
            if ($evt['channel'] === $channel && $evt['event'] === $eventName) {
                $found = true;
                break;
            }
        }

        \PHPUnit\Framework\Assert::assertTrue($found, "Failed asserting that event '{$eventName}' was broadcast to channel '{$channel}'.");
    }

    public static function assertBroadcastToRoom(string $roomCode, string $eventName): void
    {
        $cleanCode = strtoupper(trim($roomCode));
        $channel = "room-{$cleanCode}";
        self::assertBroadcast($channel, $eventName);
    }

    /**
     * Resolve Pusher configuration dynamically without hardcoded secrets.
     */
    public static function getCredentials(): array
    {
        return [
            'app_id'  => (string) (config('services.pusher.app_id') ?: env('PUSHER_APP_ID', '')),
            'key'     => (string) (config('services.pusher.key') ?: env('PUSHER_KEY', env('PUSHER_APP_KEY', ''))),
            'secret'  => (string) (config('services.pusher.secret') ?: env('PUSHER_SECRET', env('PUSHER_APP_SECRET', ''))),
            'cluster' => (string) (config('services.pusher.cluster') ?: env('PUSHER_CLUSTER', env('PUSHER_APP_CLUSTER', 'mt1'))),
        ];
    }

    /**
     * Broadcast an event to a room channel: room-{roomCode}
     */
    public static function broadcastToRoom(string $roomCode, string $eventName, array $data): bool
    {
        if (empty($roomCode)) {
            return false;
        }

        $cleanCode = strtoupper(trim($roomCode));
        $channel = "room-{$cleanCode}";

        return self::trigger($channel, $eventName, $data);
    }

    /**
     * Broadcast an event globally (or to a specific channel)
     */
    public static function broadcastGlobal(string $eventName, array $data): bool
    {
        return self::trigger('global', $eventName, $data);
    }

    /**
     * Trigger event on a Pusher channel
     */
    public static function trigger(string $channel, string $eventName, array $data): bool
    {
        $startTime = microtime(true);

        if (self::$fake) {
            $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE);
            $bytes = strlen($jsonPayload ?: '');
            self::$dispatchedEvents[] = [
                'channel' => $channel,
                'event' => $eventName,
                'data' => $data,
                'timestamp' => microtime(true),
            ];
            self::recordTransmission($channel, $eventName, $bytes, true, 200, 1.5);
            return true;
        }

        $creds = self::getCredentials();
        $appId = $creds['app_id'];
        $key = $creds['key'];
        $secret = $creds['secret'];
        $cluster = $creds['cluster'];

        if (empty($appId) || empty($key) || empty($secret)) {
            Log::warning("[PusherService] Missing credentials, skipping event {$eventName} on {$channel}");
            self::recordTransmission($channel, $eventName, 0, false, 0, 0.0);
            return false;
        }

        try {
            $body = json_encode([
                'name' => $eventName,
                'channel' => $channel,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ], JSON_UNESCAPED_UNICODE);

            $bytes = strlen($body ?: '');
            $bodyMd5 = md5($body);
            $timestamp = time();

            $params = [
                'auth_key' => $key,
                'auth_timestamp' => $timestamp,
                'auth_version' => '1.0',
                'body_md5' => $bodyMd5,
            ];
            ksort($params);
            $queryString = http_build_query($params);

            $stringToSign = "POST\n/apps/{$appId}/events\n{$queryString}";
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            $url = "https://api-{$cluster}.pusher.com/apps/{$appId}/events?{$queryString}&auth_signature={$signature}";

            $client = self::getHttpClient();
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
            ]);

            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
            $status = $response->getStatusCode();
            $success = ($status >= 200 && $status < 300);

            self::recordTransmission($channel, $eventName, $bytes, $success, $status, $elapsedMs);

            if ($success) {
                return true;
            }

            Log::error("[PusherService] Error {$status}: " . (string) $response->getBody());
            return false;
        } catch (\Throwable $e) {
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
            self::recordTransmission($channel, $eventName, 0, false, 500, $elapsedMs);
            Log::error("[PusherService] Exception trigger ({$eventName} on {$channel}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record usage statistics & transmission logs.
     */
    public static function recordTransmission(
        string $channel,
        string $eventName,
        int $bytes,
        bool $success,
        int $httpCode = 200,
        float $durationMs = 0.0
    ): void {
        try {
            $today = date('Y-m-d');
            $cacheTtl = 86400 * 30; // 30 days retention

            // Daily Counters
            Cache::increment("pusher_msgs_today_{$today}");
            Cache::increment("pusher_bytes_today_{$today}", max(1, $bytes));

            // Totals
            Cache::increment('pusher_msgs_total');
            Cache::increment('pusher_bytes_total', max(1, $bytes));

            if (!$success) {
                Cache::increment('pusher_errors_total');
            }

            // Events breakdown
            $events = Cache::get('pusher_events_breakdown', []);
            $events[$eventName] = ($events[$eventName] ?? 0) + 1;
            Cache::put('pusher_events_breakdown', $events, $cacheTtl);

            // Channels breakdown (keep top 50)
            $channels = Cache::get('pusher_channels_breakdown', []);
            $channels[$channel] = ($channels[$channel] ?? 0) + 1;
            if (count($channels) > 50) {
                arsort($channels);
                $channels = array_slice($channels, 0, 50, true);
            }
            Cache::put('pusher_channels_breakdown', $channels, $cacheTtl);

            // Circular recent transmissions log (latest 100)
            $recent = Cache::get('pusher_recent_transmissions', []);
            array_unshift($recent, [
                'id' => uniqid('tx_'),
                'timestamp' => date('Y-m-d H:i:s'),
                'channel' => $channel,
                'event' => $eventName,
                'bytes' => $bytes,
                'duration_ms' => round($durationMs, 2),
                'status_code' => $httpCode,
                'success' => $success,
            ]);

            if (count($recent) > 100) {
                $recent = array_slice($recent, 0, 100);
            }
            Cache::put('pusher_recent_transmissions', $recent, $cacheTtl);
        } catch (\Throwable $e) {
            Log::warning("[PusherService] recordTransmission failed: " . $e->getMessage());
        }
    }

    /**
     * Query live occupied channels from Pusher REST API.
     */
    public static function getPusherApiChannels(?string $prefix = null): array
    {
        $creds = self::getCredentials();
        $appId = $creds['app_id'];
        $key = $creds['key'];
        $secret = $creds['secret'];
        $cluster = $creds['cluster'];

        if (empty($appId) || empty($key) || empty($secret)) {
            return ['channels' => []];
        }

        try {
            $params = [
                'auth_key' => $key,
                'auth_timestamp' => time(),
                'auth_version' => '1.0',
            ];
            if ($prefix) {
                $params['filter_by_prefix'] = $prefix;
            }
            ksort($params);
            $queryString = http_build_query($params);

            $stringToSign = "GET\n/apps/{$appId}/channels\n{$queryString}";
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            $url = "https://api-{$cluster}.pusher.com/apps/{$appId}/channels?{$queryString}&auth_signature={$signature}";

            $client = self::getHttpClient();
            $response = $client->get($url);

            if ($response->getStatusCode() === 200) {
                $data = json_decode((string)$response->getBody(), true);
                return is_array($data) ? $data : ['channels' => []];
            }
        } catch (\Throwable $e) {
            Log::warning("[PusherService] getPusherApiChannels failed: " . $e->getMessage());
        }

        return ['channels' => []];
    }

    /**
     * Query specific channel details from Pusher REST API.
     */
    public static function getPusherChannelDetails(string $channelName): ?array
    {
        $creds = self::getCredentials();
        $appId = $creds['app_id'];
        $key = $creds['key'];
        $secret = $creds['secret'];
        $cluster = $creds['cluster'];

        if (empty($appId) || empty($key) || empty($secret)) {
            return null;
        }

        try {
            $params = [
                'auth_key' => $key,
                'auth_timestamp' => time(),
                'auth_version' => '1.0',
                'info' => 'subscription_count,user_count',
            ];
            ksort($params);
            $queryString = http_build_query($params);

            $stringToSign = "GET\n/apps/{$appId}/channels/{$channelName}\n{$queryString}";
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            $url = "https://api-{$cluster}.pusher.com/apps/{$appId}/channels/{$channelName}?{$queryString}&auth_signature={$signature}";

            $client = self::getHttpClient();
            $response = $client->get($url);

            if ($response->getStatusCode() === 200) {
                return json_decode((string)$response->getBody(), true);
            }
        } catch (\Throwable $e) {
            Log::warning("[PusherService] getPusherChannelDetails failed for {$channelName}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Measure network roundtrip ping & connectivity to Pusher.
     */
    public static function ping(): array
    {
        $startTime = microtime(true);
        $creds = self::getCredentials();
        $appId = $creds['app_id'];
        $key = $creds['key'];
        $secret = $creds['secret'];
        $cluster = $creds['cluster'];

        if (empty($appId) || empty($key) || empty($secret)) {
            return [
                'success' => false,
                'latency_ms' => 0,
                'status_code' => 0,
                'cluster' => $cluster,
                'app_id' => $appId,
                'timestamp' => date('Y-m-d H:i:s'),
                'error' => 'بيانات اعتماد Pusher غير مكتملة في ملف الإعدادات.',
            ];
        }

        try {
            $params = [
                'auth_key' => $key,
                'auth_timestamp' => time(),
                'auth_version' => '1.0',
            ];
            ksort($params);
            $queryString = http_build_query($params);

            $stringToSign = "GET\n/apps/{$appId}/channels\n{$queryString}";
            $signature = hash_hmac('sha256', $stringToSign, $secret);
            $url = "https://api-{$cluster}.pusher.com/apps/{$appId}/channels?{$queryString}&auth_signature={$signature}";

            $client = self::getHttpClient();
            $response = $client->get($url);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $statusCode = $response->getStatusCode();
            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'latency_ms' => $elapsedMs,
                'status_code' => $statusCode,
                'cluster' => $cluster,
                'app_id' => $appId,
                'timestamp' => date('Y-m-d H:i:s'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
            return [
                'success' => false,
                'latency_ms' => $elapsedMs,
                'status_code' => 500,
                'cluster' => $cluster,
                'app_id' => $appId,
                'timestamp' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Compile overall Pusher usage statistics and metrics.
     */
    public static function getUsageStats(): array
    {
        $today = date('Y-m-d');
        $msgsToday = (int) Cache::get("pusher_msgs_today_{$today}", 0);
        $bytesToday = (int) Cache::get("pusher_bytes_today_{$today}", 0);
        $msgsTotal = (int) Cache::get('pusher_msgs_total', 0);
        $bytesTotal = (int) Cache::get('pusher_bytes_total', 0);
        $errorsTotal = (int) Cache::get('pusher_errors_total', 0);

        // Standard Pusher sandbox tier quota: 200,000 messages / day
        $dailyLimit = (int) config('services.pusher.daily_limit', env('PUSHER_DAILY_LIMIT', 200000));
        $quotaPercentage = $dailyLimit > 0 ? round(($msgsToday / $dailyLimit) * 100, 2) : 0;
        $remainingToday = max(0, $dailyLimit - $msgsToday);

        $eventsBreakdown = Cache::get('pusher_events_breakdown', []);
        arsort($eventsBreakdown);

        $channelsBreakdown = Cache::get('pusher_channels_breakdown', []);
        arsort($channelsBreakdown);

        $recentTransmissions = Cache::get('pusher_recent_transmissions', []);

        // Active rooms in memory
        $activeRooms = RoomManager::getAllActiveRooms();
        $activeRoomsCount = count($activeRooms);

        $creds = self::getCredentials();
        $appId = $creds['app_id'];
        $key = $creds['key'];
        $cluster = $creds['cluster'];
        $maskedKey = strlen($key) > 8 ? substr($key, 0, 4) . '...' . substr($key, -4) : $key;

        return [
            'config' => [
                'app_id' => $appId,
                'cluster' => $cluster,
                'masked_key' => $maskedKey,
                'daily_limit' => $dailyLimit,
            ],
            'today' => [
                'messages' => $msgsToday,
                'bytes' => $bytesToday,
                'bytes_formatted' => self::formatBytes($bytesToday),
                'quota_percentage' => $quotaPercentage,
                'remaining' => $remainingToday,
            ],
            'totals' => [
                'messages' => $msgsTotal,
                'bytes' => $bytesTotal,
                'bytes_formatted' => self::formatBytes($bytesTotal),
                'errors' => $errorsTotal,
                'success_rate' => $msgsTotal > 0 ? round((($msgsTotal - $errorsTotal) / $msgsTotal) * 100, 1) : 100.0,
            ],
            'active_rooms_count' => $activeRoomsCount,
            'events_breakdown' => $eventsBreakdown,
            'channels_breakdown' => array_slice($channelsBreakdown, 0, 20, true),
            'recent_transmissions' => $recentTransmissions,
        ];
    }

    /**
     * Clear recorded usage stats.
     */
    public static function resetStats(): void
    {
        $today = date('Y-m-d');
        Cache::forget("pusher_msgs_today_{$today}");
        Cache::forget("pusher_bytes_today_{$today}");
        Cache::forget('pusher_msgs_total');
        Cache::forget('pusher_bytes_total');
        Cache::forget('pusher_errors_total');
        Cache::forget('pusher_events_breakdown');
        Cache::forget('pusher_channels_breakdown');
        Cache::forget('pusher_recent_transmissions');
    }

    /**
     * Helper to format raw bytes into human-readable representation.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        if ($bytes === 0) return '0 B';
        $pow = floor(log($bytes) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $val = $bytes / (1024 ** $pow);
        return round($val, $precision) . ' ' . $units[$pow];
    }

    private static function getHttpClient(): Client
    {
        if (!self::$httpClient) {
            self::$httpClient = new Client([
                'timeout' => 4.0,
                'connect_timeout' => 2.0,
                'http_errors' => false,
            ]);
        }
        return self::$httpClient;
    }
}
