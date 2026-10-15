<?php

namespace App\Services;

use GuzzleHttp\Client;
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
        if (self::$fake) {
            self::$dispatchedEvents[] = [
                'channel' => $channel,
                'event' => $eventName,
                'data' => $data,
                'timestamp' => microtime(true),
            ];
            return true;
        }

        $appId = config('services.pusher.app_id', env('PUSHER_APP_ID', '2190859'));
        $key = config('services.pusher.key', env('PUSHER_KEY', env('PUSHER_APP_KEY', '3f174c9cbe3c9757ce57')));
        $secret = config('services.pusher.secret', env('PUSHER_SECRET', env('PUSHER_APP_SECRET', '07621a4662e103902400')));
        $cluster = config('services.pusher.cluster', env('PUSHER_CLUSTER', env('PUSHER_APP_CLUSTER', 'mt1')));

        if (empty($appId) || empty($key) || empty($secret)) {
            Log::warning("[PusherService] Missing credentials, skipping event {$eventName} on {$channel}");
            return false;
        }

        try {
            $body = json_encode([
                'name' => $eventName,
                'channel' => $channel,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ], JSON_UNESCAPED_UNICODE);

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

            if (!self::$httpClient) {
                self::$httpClient = new Client([
                    'timeout' => 4.0,
                    'connect_timeout' => 2.0,
                    'http_errors' => false,
                ]);
            }

            $response = self::$httpClient->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return true;
            }

            Log::error("[PusherService] Error {$status}: " . (string) $response->getBody());
            return false;
        } catch (\Throwable $e) {
            Log::error("[PusherService] Exception trigger ({$eventName} on {$channel}): " . $e->getMessage());
            return false;
        }
    }
}
