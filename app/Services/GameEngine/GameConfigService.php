<?php

namespace App\Services\GameEngine;

use App\Models\GameConfiguration;
use Illuminate\Support\Facades\Cache;

class GameConfigService
{
    private const CACHE_KEY = 'deceit_live_game_config';
    private const CACHE_TTL = 3600; // 1 hour (invalidated on update)

    public const DEFAULTS = [
        'day_timer_seconds'         => 120,
        'night_timer_seconds'       => 60,
        'voting_timer_seconds'      => 60,
        'role_reveal_timer_seconds' => 8,
        'disabled_roles'            => [],
        'win_coin_reward'           => 50,
        'loss_coin_reward'          => 10,
        'tie_rule'                  => 'NO_ELIMINATION',
        'king_must_survive'         => true,
    ];

    /**
     * Get a configuration value with fallback to defaults.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::getAll();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * Set a configuration value and invalidate cache.
     */
    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        $serializedValue = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;

        GameConfiguration::updateOrCreate(
            ['key' => $key],
            ['value' => $serializedValue, 'description' => $description]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all configurations merged with defaults.
     */
    public static function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $configs = self::DEFAULTS;

            try {
                $dbEntries = GameConfiguration::all();
                foreach ($dbEntries as $entry) {
                    $val = $entry->value;
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                        $val = $decoded;
                    } elseif (is_numeric($val)) {
                        $val = str_contains($val, '.') ? (float)$val : (int)$val;
                    } elseif ($val === 'true' || $val === 'false') {
                        $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                    }
                    $configs[$entry->key] = $val;
                }
            } catch (\Throwable $e) {
                // If DB is temporarily unavailable, use defaults
            }

            return $configs;
        });
    }

    /**
     * Get active timers array for match rooms.
     */
    public static function getTimers(): array
    {
        return [
            'dayTimerSeconds'         => (int)self::get('day_timer_seconds', 120),
            'nightTimerSeconds'       => (int)self::get('night_timer_seconds', 60),
            'votingTimerSeconds'      => (int)self::get('voting_timer_seconds', 60),
            'roleRevealTimerSeconds'  => (int)self::get('role_reveal_timer_seconds', 8),
        ];
    }

    /**
     * Get disabled role IDs array.
     */
    public static function getDisabledRoles(): array
    {
        $roles = self::get('disabled_roles', []);
        return is_array($roles) ? $roles : [];
    }

    /**
     * Checks if a role is disabled globally by admin.
     */
    public static function isRoleDisabled(string $role): bool
    {
        return in_array($role, self::getDisabledRoles(), true);
    }

    /**
     * Clear live cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

