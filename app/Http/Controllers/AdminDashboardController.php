<?php

namespace App\Http\Controllers;

use App\Models\AppIssue;
use App\Models\AuditLog;
use App\Models\MatchLog;
use App\Models\PlayerProfile;
use App\Models\SuspiciousActivity;
use App\Models\UserFeedback;
use App\Services\Analytics\RoleAnalyticsService;
use App\Services\GameEngine\GameConfigService;
use App\Services\GameEngine\RoleData;
use App\Services\GameEngine\RoomManager;
use App\Services\GameEngine\StressTestService;
use App\Services\PusherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    /**
     * Process admin login.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $credentials['login'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $login, 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'ADMIN_LOGIN',
                'details' => json_encode(['ip' => $request->ip(), 'user_agent' => $request->userAgent()]),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'login' => 'بيانات تسجيل الدخول غير صحيحة.',
        ])->onlyInput('login');
    }

    /**
     * Logout admin.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'ADMIN_LOGOUT',
                'details' => json_encode(['ip' => $request->ip()]),
                'ip_address' => $request->ip(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Admin Main Dashboard with Overview & Live State.
     */
    public function dashboard(): View
    {
        $totalPlayers = PlayerProfile::count();
        $totalMatches = MatchLog::count();
        $totalIssues = AppIssue::count();
        $avgRating = round((float)UserFeedback::avg('rating') ?: 5.0, 1);

        $activeRooms = RoomManager::getAllActiveRooms();
        $recentPlayers = PlayerProfile::orderBy('updated_at', 'desc')->take(8)->get();
        $recentIssues = AppIssue::orderBy('created_at', 'desc')->take(5)->get();

        // Analytics calculation
        $kingdomWins = MatchLog::where('winning_faction', 'like', '%KINGDOM%')->count();
        $shadowWins = MatchLog::where('winning_faction', 'like', '%SHADOW%')->count();
        $neutralWins = MatchLog::where(function ($q) {
            $q->where('winning_faction', 'like', '%SOLO%')
              ->orWhere('winning_faction', 'like', '%NEUTRAL%')
              ->orWhere('winning_faction', 'like', '%TRICKSTER%');
        })->count();

        return view('admin.dashboard', compact(
            'totalPlayers',
            'totalMatches',
            'totalIssues',
            'avgRating',
            'activeRooms',
            'recentPlayers',
            'recentIssues',
            'kingdomWins',
            'shadowWins',
            'neutralWins'
        ));
    }

    /**
     * Live Rooms Screen (Live match operations).
     */
    public function rooms(): View
    {
        $activeRooms = RoomManager::getAllActiveRooms();
        return view('admin.rooms', compact('activeRooms'));
    }

    /**
     * Admin adds bot to live room.
     */
    public function addBotToRoom(string $roomCode): RedirectResponse
    {
        $result = RoomManager::addBot($roomCode);
        if (isset($result['error'])) {
            return back()->with('error', 'تعذر إضافة بوت: ' . $result['error']);
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ADMIN_ADD_BOT',
            'details' => json_encode(['roomCode' => $roomCode, 'bot' => $result['bot']['nickname'] ?? '']),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', "تمت إضافة البوت للغرفة {$roomCode} بنجاح.");
    }

    /**
     * Force room to skip to next phase.
     */
    public function nextPhase(string $roomCode): RedirectResponse
    {
        $room = RoomManager::getRoom($roomCode);
        if (!$room) {
            return back()->with('error', 'الغرفة غير موجودة');
        }

        RoomManager::advanceMatchLoop($room);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'FORCE_NEXT_PHASE',
            'details' => json_encode(['roomCode' => $roomCode, 'newPhase' => $room['phase'] ?? '']),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', "تم تمرير مرحلة الغرفة {$roomCode} فورياً بنجاح.");
    }

    /**
     * Close and destroy live room.
     */
    public function destroyRoom(string $roomCode): RedirectResponse
    {
        RoomManager::deleteRoom($roomCode);
        PusherService::broadcastToRoom($roomCode, 's_room_destroyed', [
            'reason' => 'تم إغلاق الغرفة بواسطة المشرف.',
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DESTROY_ROOM',
            'details' => json_encode(['roomCode' => $roomCode]),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', "تم إنهاء وإغلاق الغرفة {$roomCode} بنجاح.");
    }

    /**
     * Kick player from room.
     */
    public function kickRoomPlayer(string $roomCode, string $playerId): RedirectResponse
    {
        $room = RoomManager::getRoom($roomCode);
        if ($room) {
            $hostId = $room['hostPlayerId'] ?? '';
            RoomManager::kickPlayer($roomCode, $hostId, $playerId);
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'ADMIN_KICK_PLAYER',
            'details' => json_encode(['roomCode' => $roomCode, 'playerId' => $playerId]),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', "تم طرد اللاعب من الغرفة {$roomCode}.");
    }

    /**
     * Send global announcement / broadcast to all connected players.
     */
    public function broadcast(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $message = trim($validated['message']);
        $payload = [
            'id' => (string)time(),
            'message' => $message,
            'timestamp' => (int)(microtime(true) * 1000),
        ];

        // Trigger on global channel and all active room channels
        \Illuminate\Support\Facades\Cache::put('latest_system_broadcast', $payload, now()->addHours(24));
        PusherService::broadcastGlobal('s_system_broadcast', $payload);
        foreach (RoomManager::getAllActiveRooms() as $r) {
            if (!empty($r['roomCode'])) {
                PusherService::broadcastToRoom($r['roomCode'], 's_system_broadcast', $payload);
            }
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'GLOBAL_BROADCAST',
            'details' => json_encode(['message' => $message]),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'تم إرسال التنبيه العام بنجاح لجميع اللاعبين المتصلين.');
    }

    /**
     * Game Analytics View.
     */
    public function analytics(): View
    {
        $totalMatches = MatchLog::count();
        $avgDuration = round((float)MatchLog::avg('duration_seconds') ?: 0);

        $kingdomWins = MatchLog::where('winning_faction', 'like', '%KINGDOM%')->count();
        $shadowWins = MatchLog::where('winning_faction', 'like', '%SHADOW%')->count();
        $neutralWins = MatchLog::where(function ($q) {
            $q->where('winning_faction', 'like', '%SOLO%')
              ->orWhere('winning_faction', 'like', '%NEUTRAL%')
              ->orWhere('winning_faction', 'like', '%TRICKSTER%');
        })->count();

        $kingdomPercent = $totalMatches > 0 ? round(($kingdomWins / $totalMatches) * 100, 1) : 0;
        $shadowPercent = $totalMatches > 0 ? round(($shadowWins / $totalMatches) * 100, 1) : 0;
        $neutralPercent = $totalMatches > 0 ? round(($neutralWins / $totalMatches) * 100, 1) : 0;

        $matches = MatchLog::orderBy('created_at', 'desc')->paginate(15);

        $roleMatrix = RoleAnalyticsService::getRoleMatrix();
        $economyMetrics = RoleAnalyticsService::getEconomyMetrics();

        return view('admin.analytics', compact(
            'totalMatches',
            'avgDuration',
            'kingdomWins',
            'shadowWins',
            'neutralWins',
            'kingdomPercent',
            'shadowPercent',
            'neutralPercent',
            'matches',
            'roleMatrix',
            'economyMetrics'
        ));
    }

    /**
     * Players Directory & Profile Management.
     */
    public function players(Request $request): View
    {
        $query = PlayerProfile::query();
        if ($search = $request->input('search')) {
            $query->where('nickname', 'like', "%{$search}%")
                  ->orWhere('player_id', 'like', "%{$search}%");
        }

        $players = $query->orderBy('updated_at', 'desc')->paginate(20);
        return view('admin.players', compact('players'));
    }

    /**
     * Update coins for a player.
     */
    public function updateCoins(Request $request, string $id): RedirectResponse
    {
        $request->validate(['coins' => 'required|integer|min:0']);
        $player = PlayerProfile::findOrFail($id);
        $oldCoins = $player->coins;
        $player->update(['coins' => $request->input('coins')]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE_COINS',
            'details' => json_encode(['playerId' => $id, 'old' => $oldCoins, 'new' => $player->coins]),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "تم تحديث عملات اللاعب {$player->nickname} إلى {$player->coins}.");
    }

    /**
     * Toggle ban status for a player.
     */
    public function toggleBan(string $id): RedirectResponse
    {
        $player = PlayerProfile::findOrFail($id);
        $player->is_banned = !$player->is_banned;
        $player->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $player->is_banned ? 'BAN_PLAYER' : 'UNBAN_PLAYER',
            'details' => json_encode(['playerId' => $id, 'nickname' => $player->nickname]),
            'ip_address' => request()->ip(),
        ]);

        $status = $player->is_banned ? 'حظر' : 'إلغاء حظر';
        return back()->with('success', "تم {$status} اللاعب {$player->nickname} بنجاح.");
    }

    /**
     * Issues & Error Logs.
     */
    public function issues(Request $request): View
    {
        $query = AppIssue::query();
        if ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }

        $issues = $query->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.issues', compact('issues'));
    }

    /**
     * User Feedback Screen.
     */
    public function feedback(): View
    {
        $feedbacks = UserFeedback::orderBy('created_at', 'desc')->paginate(20);
        $avgRating = round((float)UserFeedback::avg('rating') ?: 5.0, 1);
        $totalFeedbacks = UserFeedback::count();

        return view('admin.feedback', compact('feedbacks', 'avgRating', 'totalFeedbacks'));
    }

    /**
     * Audit Logs Screen.
     */
    public function auditLogs(): View
    {
        $logs = AuditLog::orderBy('created_at', 'desc')->paginate(30);
        return view('admin.audit-logs', compact('logs'));
    }

    /**
     * Live Game Configuration & Hot-Tuning View.
     */
    public function tuning(): View
    {
        $configs = GameConfigService::getAll();
        $allRoles = RoleData::getAllRoles();
        $disabledRoles = GameConfigService::getDisabledRoles();

        return view('admin.tuning', compact('configs', 'allRoles', 'disabledRoles'));
    }

    /**
     * Update Live Game Configuration.
     */
    public function updateTuning(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dayTimerSeconds'         => 'required|integer|min:15|max:600',
            'nightTimerSeconds'       => 'required|integer|min:15|max:300',
            'votingTimerSeconds'      => 'required|integer|min:15|max:300',
            'roleRevealTimerSeconds'  => 'required|integer|min:3|max:30',
            'winCoinReward'           => 'required|integer|min:0|max:1000',
            'lossCoinReward'          => 'required|integer|min:0|max:500',
            'tieRule'                 => 'required|string|in:NO_ELIMINATION,RANDOM_ELIMINATION',
            'kingMustSurvive'         => 'nullable|boolean',
            'disabledRoles'           => 'nullable|array',
            'disabledRoles.*'         => 'string',
        ]);

        GameConfigService::set('day_timer_seconds', $validated['dayTimerSeconds'], 'Day cycle duration in seconds');
        GameConfigService::set('night_timer_seconds', $validated['nightTimerSeconds'], 'Night cycle duration in seconds');
        GameConfigService::set('voting_timer_seconds', $validated['votingTimerSeconds'], 'Daytime voting duration in seconds');
        GameConfigService::set('role_reveal_timer_seconds', $validated['roleRevealTimerSeconds'], 'Role reveal banner duration');
        GameConfigService::set('win_coin_reward', $validated['winCoinReward'], 'Coins awarded to match winners');
        GameConfigService::set('loss_coin_reward', $validated['lossCoinReward'], 'Coins awarded to match losers');
        GameConfigService::set('tie_rule', $validated['tieRule'], 'Voting tie breaker behavior');
        GameConfigService::set('king_must_survive', $request->boolean('kingMustSurvive'), 'Kingdom condition: King must survive');
        GameConfigService::set('disabled_roles', $request->input('disabledRoles', []), 'List of temporarily disabled role IDs');

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE_GAME_TUNING',
            'details' => json_encode($validated),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'تم حفظ وتطبيق إعدادات اللعبة الحية فورياً بنجاح.');
    }

    /**
     * Anti-Teaming & Moderation Suite.
     */
    public function moderation(Request $request): View
    {
        $statusFilter = $request->input('status', 'all');
        $query = SuspiciousActivity::query()->with(['playerA', 'playerB']);

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $activities = $query->orderBy('created_at', 'desc')->paginate(20);
        $pendingAlertsCount = SuspiciousActivity::where('status', 'pending')->count();
        $shadowBannedCount = PlayerProfile::where('is_shadow_banned', true)->count();
        $mutedCount = PlayerProfile::whereNotNull('muted_until')->where('muted_until', '>', now())->count();

        return view('admin.moderation', compact('activities', 'pendingAlertsCount', 'shadowBannedCount', 'mutedCount', 'statusFilter'));
    }

    /**
     * Toggle shadow ban on a player.
     */
    public function toggleShadowBan(string $id): RedirectResponse
    {
        $player = PlayerProfile::findOrFail($id);
        $player->is_shadow_banned = !$player->is_shadow_banned;
        $player->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $player->is_shadow_banned ? 'SHADOW_BAN_PLAYER' : 'UN_SHADOW_BAN_PLAYER',
            'details' => json_encode(['playerId' => $id, 'nickname' => $player->nickname]),
            'ip_address' => request()->ip(),
        ]);

        $status = $player->is_shadow_banned ? 'تفعيل الحظر الخفي' : 'إلغاء الحظر الخفي';
        return back()->with('success', "تم {$status} للاعب {$player->nickname} بنجاح.");
    }

    /**
     * Mute player from game chat for specified duration in minutes.
     */
    public function mutePlayer(Request $request, string $id): RedirectResponse
    {
        $durationMinutes = (int)$request->input('duration', 1440); // default 24h
        $player = PlayerProfile::findOrFail($id);

        if ($durationMinutes <= 0) {
            $player->muted_until = null;
            $msg = "تم رفع الكتم عن اللاعب {$player->nickname}.";
            $action = 'UNMUTE_PLAYER';
        } else {
            $player->muted_until = now()->addMinutes($durationMinutes);
            $hours = round($durationMinutes / 60, 1);
            $msg = "تم كتم اللاعب {$player->nickname} لمدة {$hours} ساعة.";
            $action = 'MUTE_PLAYER';
        }

        $player->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'details' => json_encode(['playerId' => $id, 'durationMinutes' => $durationMinutes]),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', $msg);
    }

    /**
     * Dismiss or delete suspicious activity alert.
     */
    public function dismissSuspiciousActivity(string $id): RedirectResponse
    {
        $activity = SuspiciousActivity::findOrFail($id);
        $activity->status = 'dismissed';
        $activity->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DISMISS_SUSPICIOUS_ACTIVITY',
            'details' => json_encode(['activityId' => $id, 'reason' => $activity->reason]),
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'تم تجاهل البلاغ بنجاح.');
    }

    /**
     * Live God-Mode Room Spectator Screen.
     */
    public function spectator(string $roomCode): View|RedirectResponse
    {
        $code = strtoupper(trim($roomCode));
        $snapshot = RoomManager::getSpectatorSnapshot($code);

        if (!$snapshot) {
            return redirect()->route('admin.rooms')->with('error', "الغرفة {$code} غير موجودة أو انتهت صلاحيتها.");
        }

        return view('admin.spectator', compact('snapshot', 'code'));
    }

    /**
     * Unredacted JSON snapshot endpoint for live AJAX polling.
     */
    public function spectatorSnapshot(string $roomCode): JsonResponse
    {
        $code = strtoupper(trim($roomCode));
        $snapshot = RoomManager::getSpectatorSnapshot($code);

        if (!$snapshot) {
            return response()->json(['error' => 'ROOM_NOT_FOUND'], 404);
        }

        return response()->json([
            'success' => true,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Bot Stress-Testing Panel Screen.
     */
    public function stressTest(): View
    {
        $lastResult = session('stress_test_result');
        return view('admin.stress-test', compact('lastResult'));
    }

    /**
     * Trigger bot stress-test simulation.
     */
    public function runStressTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'roomCount'   => 'required|integer|min:1|max:20',
            'botsPerRoom' => 'required|integer|min:3|max:16',
        ]);

        $result = StressTestService::runSimulation(
            (int)$validated['roomCount'],
            (int)$validated['botsPerRoom']
        );

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'RUN_BOT_STRESS_TEST',
            'details' => json_encode([
                'roomCount' => $validated['roomCount'],
                'botsPerRoom' => $validated['botsPerRoom'],
                'executionTimeMs' => $result['executionTimeMs'],
                'totalPlayers' => $result['totalPlayers'],
            ]),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.stressTest')
            ->with('stress_test_result', $result)
            ->with('success', "اكتملت محاكاة {$result['roomsSpawned']} غرف بنجاح خلال {$result['executionTimeMs']} ميلي ثانية!");
    }

    /**
     * Pusher Usage & Live WebSocket Monitor Screen.
     */
    public function pusherMonitor(): View
    {
        $stats = PusherService::getUsageStats();
        $channelsApi = PusherService::getPusherApiChannels('room-');
        $ping = PusherService::ping();

        return view('admin.pusher', compact('stats', 'channelsApi', 'ping'));
    }

    /**
     * Live stats endpoint for AJAX polling.
     */
    public function pusherLiveStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats' => PusherService::getUsageStats(),
            'channelsApi' => PusherService::getPusherApiChannels('room-'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ping Pusher server endpoint.
     */
    public function pusherPing(): JsonResponse
    {
        return response()->json(PusherService::ping());
    }

    /**
     * Trigger a test broadcast event to a channel.
     */
    public function pusherSendTestEvent(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|max:100',
            'event'   => 'required|string|max:100',
            'message' => 'nullable|string|max:500',
        ]);

        $channel = trim($validated['channel']);
        $event = trim($validated['event']);
        $msg = $validated['message'] ?: 'Live test broadcast from admin monitor';

        $data = [
            'test'      => true,
            'message'   => $msg,
            'sender'    => Auth::user()->username ?? 'Admin',
            'timestamp' => time(),
        ];

        $success = PusherService::trigger($channel, $event, $data);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'PUSHER_TEST_EVENT',
            'details' => json_encode(['channel' => $channel, 'event' => $event, 'success' => $success]),
            'ip_address' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'channel' => $channel,
                'event' => $event,
                'message' => $success ? "تم إرسال الحدث إلى {$channel} بنجاح!" : "فشل الإرسال إلى Pusher.",
            ]);
        }

        return redirect()->route('admin.pusher')
            ->with($success ? 'success' : 'error', $success ? "تم إرسال الحدث {$event} إلى القناة {$channel} بنجاح!" : "فشل إرسال الحدث إلى Pusher.");
    }

    /**
     * Inspect channel details from Pusher REST API.
     */
    public function pusherInspectChannel(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|max:100',
        ]);

        $channel = trim($request->input('channel'));
        $details = PusherService::getPusherChannelDetails($channel);

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'details' => $details,
        ]);
    }

    /**
     * Reset recorded usage statistics.
     */
    public function pusherResetStats(Request $request): JsonResponse|RedirectResponse
    {
        PusherService::resetStats();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'RESET_PUSHER_STATS',
            'details' => json_encode(['ip' => $request->ip()]),
            'ip_address' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تصفير إحصائيات Pusher بنجاح.',
            ]);
        }

        return redirect()->route('admin.pusher')
            ->with('success', 'تم تصفير إحصائيات استهلاك Pusher بنجاح.');
    }
}
