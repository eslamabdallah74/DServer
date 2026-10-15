<?php

namespace App\Services\Analytics;

use App\Models\MatchLog;
use App\Models\PlayerProfile;
use App\Services\GameEngine\RoleData;

class RoleAnalyticsService
{
    /**
     * Compute Role Win-Rate Matrix & Balance Indicators across historical matches.
     */
    public static function getRoleMatrix(): array
    {
        $allRoles = RoleData::getAllRoles();
        $stats = [];

        foreach ($allRoles as $role => $faction) {
            $stats[$role] = [
                'role'           => $role,
                'faction'        => $faction,
                'appearances'    => 0,
                'wins'           => 0,
                'losses'         => 0,
                'win_rate'       => 50.0, // baseline
                'deaths'         => 0,
                'survival_rate'  => 50.0,
                'status'         => 'BALANCED',
            ];
        }

        // Scan match logs
        $matches = MatchLog::all();
        foreach ($matches as $match) {
            $winningFaction = strtolower($match->winning_faction ?? '');
            $players = $match->players_json ?? [];

            foreach ($players as $p) {
                $role = $p['role'] ?? null;
                if (!$role || !isset($stats[$role])) {
                    continue;
                }

                $stats[$role]['appearances']++;
                $faction = strtolower($p['faction'] ?? $stats[$role]['faction']);
                $isWinner = !empty($faction) && str_contains($winningFaction, $faction);

                if ($isWinner) {
                    $stats[$role]['wins']++;
                } else {
                    $stats[$role]['losses']++;
                }

                if (!($p['isAlive'] ?? true)) {
                    $stats[$role]['deaths']++;
                }
            }
        }

        // Calculate rates & indicators
        foreach ($stats as $role => &$item) {
            if ($item['appearances'] > 0) {
                $item['win_rate'] = round(($item['wins'] / $item['appearances']) * 100, 1);
                $item['survival_rate'] = round((($item['appearances'] - $item['deaths']) / $item['appearances']) * 100, 1);
            }

            if ($item['appearances'] >= 3) {
                if ($item['win_rate'] >= 65.0) {
                    $item['status'] = 'OVERPOWERED';
                } elseif ($item['win_rate'] <= 35.0) {
                    $item['status'] = 'UNDERPOWERED';
                } else {
                    $item['status'] = 'BALANCED';
                }
            } else {
                $item['status'] = 'INSUFFICIENT_DATA';
            }
        }

        // Sort by appearances descending
        uasort($stats, fn($a, $b) => $b['appearances'] <=> $a['appearances']);

        return $stats;
    }

    /**
     * Compute Economy Metrics (Dcoins Inflow, Outflow, Balances).
     */
    public static function getEconomyMetrics(): array
    {
        $totalCoins = (int)PlayerProfile::sum('coins');
        $totalPlayers = PlayerProfile::count();
        $avgCoins = $totalPlayers > 0 ? (int)round($totalCoins / $totalPlayers) : 0;
        $maxCoins = (int)PlayerProfile::max('coins') ?: 0;

        $topWealthy = PlayerProfile::orderBy('coins', 'desc')->take(5)->get();

        $totalMatches = MatchLog::count();
        $estimatedFaucetCoins = $totalMatches * 60; // win/loss coin rewards

        return [
            'total_coins'            => $totalCoins,
            'total_players'          => $totalPlayers,
            'avg_coins'              => $avgCoins,
            'max_coins'              => $maxCoins,
            'top_wealthy'            => $topWealthy,
            'estimated_faucet_coins' => $estimatedFaucetCoins,
        ];
    }
}

