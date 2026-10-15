<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlayerSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|string',
            'nickname' => 'nullable|string',
            'coins' => 'nullable|integer',
            'ownedRoles' => 'nullable|array',
            'stats' => 'nullable|array',
            'stats.wins' => 'nullable|integer',
            'stats.losses' => 'nullable|integer',
            'stats.matches' => 'nullable|integer',
        ]);

        $playerId = $validated['playerId'];
        $nickname = $validated['nickname'] ?? 'Player';
        $incomingCoins = $validated['coins'] ?? 500;
        $incomingRoles = $validated['ownedRoles'] ?? [];
        
        $stats = $validated['stats'] ?? [];
        $incomingWins = $stats['wins'] ?? 0;
        $incomingLosses = $stats['losses'] ?? 0;
        $incomingMatches = $stats['matches'] ?? 0;

        $existing = PlayerProfile::where('player_id', $playerId)->first();

        if (!$existing) {
            $profile = PlayerProfile::create([
                'player_id' => $playerId,
                'nickname' => $nickname,
                'coins' => max(0, $incomingCoins),
                'owned_roles_json' => json_encode(array_values(array_unique($incomingRoles))),
                'stats_wins' => max(0, $incomingWins),
                'stats_losses' => max(0, $incomingLosses),
                'stats_matches' => max(0, $incomingMatches),
                'last_seen_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'reconciled' => true,
                'profile' => [
                    'playerId' => $profile->player_id,
                    'nickname' => $profile->nickname,
                    'coins' => $profile->coins,
                    'ownedRoles' => $profile->owned_roles,
                    'stats' => [
                        'wins' => $profile->stats_wins,
                        'losses' => $profile->stats_losses,
                        'matches' => $profile->stats_matches,
                    ]
                ]
            ]);
        }

        // Reconciliation: Math.max for coins & stats, Set Union for owned roles
        $reconciledCoins = max($existing->coins, $incomingCoins);
        $reconciledWins = max($existing->stats_wins, $incomingWins);
        $reconciledLosses = max($existing->stats_losses, $incomingLosses);
        $reconciledMatches = max($existing->stats_matches, $incomingMatches);
        
        $existingRoles = $existing->owned_roles;
        $reconciledRoles = array_values(array_unique(array_merge($existingRoles, $incomingRoles)));

        $existing->update([
            'nickname' => $nickname ?: $existing->nickname,
            'coins' => $reconciledCoins,
            'owned_roles_json' => json_encode($reconciledRoles),
            'stats_wins' => $reconciledWins,
            'stats_losses' => $reconciledLosses,
            'stats_matches' => $reconciledMatches,
            'last_seen_at' => now(),
        ]);
        $existing->refresh();

        return response()->json([
            'success' => true,
            'reconciled' => true,
            'profile' => [
                'playerId' => $existing->player_id,
                'nickname' => $existing->nickname,
                'coins' => $existing->coins,
                'ownedRoles' => $existing->owned_roles,
                'stats' => [
                    'wins' => $existing->stats_wins,
                    'losses' => $existing->stats_losses,
                    'matches' => $existing->stats_matches,
                ]
            ]
        ]);
    }

    public function getPlayer(string $id): JsonResponse
    {
        $profile = PlayerProfile::find($id);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'اللاعب غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => [
                'playerId' => $profile->player_id,
                'nickname' => $profile->nickname,
                'coins' => $profile->coins,
                'ownedRoles' => $profile->owned_roles,
                'stats' => [
                    'wins' => $profile->stats_wins,
                    'losses' => $profile->stats_losses,
                    'matches' => $profile->stats_matches,
                ]
            ]
        ]);
    }

    public function upsertPlayer(Request $request): JsonResponse
    {
        return $this->sync($request);
    }

    public function getPlayers(): JsonResponse
    {
        $players = PlayerProfile::orderBy('updated_at', 'desc')->get();
        return response()->json([
            'players' => $players->map(fn($p) => [
                'playerId' => $p->player_id,
                'nickname' => $p->nickname,
                'coins' => $p->coins,
                'ownedRoles' => $p->owned_roles,
                'stats' => [
                    'wins' => $p->stats_wins,
                    'losses' => $p->stats_losses,
                    'matches' => $p->stats_matches,
                ]
            ]),
            'total' => $players->count()
        ]);
    }

    public function incrementCoins(Request $request): JsonResponse
    {
        $playerId = $request->input('playerId');
        $amount = (int)$request->input('amount', 0);

        if (!$playerId || $amount <= 0) {
            return response()->json(['success' => false, 'error' => 'INVALID_PARAMS'], 400);
        }

        $player = PlayerProfile::firstOrCreate(
            ['player_id' => $playerId],
            ['nickname' => 'لاعب_' . substr($playerId, -4), 'coins' => 100]
        );

        $player->increment('coins', $amount);

        return response()->json([
            'success' => true,
            'playerId' => $playerId,
            'coins' => $player->fresh()->coins,
            'message' => "Coins incremented by {$amount}"
        ]);
    }

    public function decrementCoins(Request $request): JsonResponse
    {
        $playerId = $request->input('playerId');
        $amount = (int)$request->input('amount', 0);

        if (!$playerId || $amount <= 0) {
            return response()->json(['success' => false, 'error' => 'INVALID_PARAMS'], 400);
        }

        $player = PlayerProfile::where('player_id', $playerId)->first();
        if (!$player || $player->coins < $amount) {
            return response()->json(['success' => false, 'error' => 'INSUFFICIENT_COINS'], 400);
        }

        $player->decrement('coins', $amount);

        return response()->json([
            'success' => true,
            'playerId' => $playerId,
            'coins' => $player->fresh()->coins,
            'message' => "Coins decremented by {$amount}"
        ]);
    }

    public function logMatch(Request $request): JsonResponse
    {
        $match = \App\Models\MatchLog::create([
            'room_code' => $request->input('roomCode', 'CUSTOM'),
            'winning_faction' => $request->input('winnerTeam', $request->input('winningFaction', 'KINGDOM')),
            'victory_rule' => $request->input('victoryRule', 'standard'),
            'player_count' => (int)$request->input('playerCount', 0),
            'duration_seconds' => (int)$request->input('durationSeconds', 0),
            'rounds_played' => (int)$request->input('roundsPlayed', 1),
            'players_summary' => $request->input('rolesUsed', []),
        ]);

        return response()->json([
            'success' => true,
            'matchId' => $match->match_id,
        ]);
    }

    public function getAnnouncement(): JsonResponse
    {
        $announcement = \Illuminate\Support\Facades\Cache::get('latest_system_broadcast');
        return response()->json([
            'announcement' => $announcement
        ]);
    }
}
