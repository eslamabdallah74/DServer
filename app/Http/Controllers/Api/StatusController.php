<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerProfile;
use App\Models\MatchLog;
use App\Models\AppIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    public function getStatus(): JsonResponse
    {
        $dbConnected = true;
        $dbError = null;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
        }

        $totalPlayers = $dbConnected ? PlayerProfile::count() : 0;
        $totalMatches = $dbConnected ? MatchLog::count() : 0;
        $totalIssues = $dbConnected ? AppIssue::count() : 0;

        return response()->json([
            'status' => 'OK',
            'server' => 'Deceit Server (Laravel 11)',
            'timestamp' => now()->toIso8601String(),
            'database' => [
                'connected' => $dbConnected,
            ],
            'metrics' => [
                'totalPlayers' => $totalPlayers,
                'totalMatches' => $totalMatches,
                'totalIssues' => $totalIssues,
                'memoryMB' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]
        ]);
    }
}
