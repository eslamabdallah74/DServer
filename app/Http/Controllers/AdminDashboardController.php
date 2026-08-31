<?php

namespace App\Http\Controllers;

use App\Models\PlayerProfile;
use App\Models\MatchLog;
use App\Models\AppIssue;
use App\Models\UserFeedback;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function dashboard(): View
    {
        $totalPlayers = PlayerProfile::count();
        $totalMatches = MatchLog::count();
        $totalIssues = AppIssue::count();
        $recentPlayers = PlayerProfile::orderBy('updated_at', 'desc')->take(10)->get();
        $recentIssues = AppIssue::orderBy('created_at', 'desc')->take(5)->get();

        return view('admin.dashboard', compact(
            'totalPlayers',
            'totalMatches',
            'totalIssues',
            'recentPlayers',
            'recentIssues'
        ));
    }

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

    public function updateCoins(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'coins' => 'required|integer|min:0',
        ]);

        $player = PlayerProfile::findOrFail($id);
        $player->update(['coins' => $request->input('coins')]);

        return redirect()->back()->with('success', "تم تحديث عملات اللاعب {$player->nickname} بنجاح.");
    }

    public function issues(): View
    {
        $issues = AppIssue::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.issues', compact('issues'));
    }
}
