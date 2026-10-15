<?php

namespace App\Http\Controllers;

use App\Models\AppIssue;
use App\Models\AuditLog;
use App\Models\MatchLog;
use App\Models\PlayerProfile;
use App\Models\UserFeedback;
use App\Services\GameEngine\RoomManager;
use App\Services\PusherService;
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

        return view('admin.analytics', compact(
            'totalMatches',
            'avgDuration',
            'kingdomWins',
            'shadowWins',
            'neutralWins',
            'kingdomPercent',
            'shadowPercent',
            'neutralPercent',
            'matches'
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
}
