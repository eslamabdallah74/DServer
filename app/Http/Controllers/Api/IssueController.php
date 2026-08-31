<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppIssue;
use App\Models\UserFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function logIssue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severity' => 'nullable|string',
            'message' => 'required|string',
            'stackTrace' => 'nullable|string',
            'page' => 'nullable|string',
            'deviceInfo' => 'nullable|array',
        ]);

        $issue = AppIssue::create([
            'severity' => $validated['severity'] ?? 'error',
            'message' => $validated['message'],
            'stack_trace' => $validated['stackTrace'] ?? null,
            'page' => $validated['page'] ?? null,
            'device_info' => $validated['deviceInfo'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'issueId' => $issue->issue_id,
        ]);
    }

    public function submitFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'nullable|string',
            'content' => 'required|string',
            'rating' => 'nullable|integer',
        ]);

        $feedback = UserFeedback::create([
            'player_id' => $validated['playerId'] ?? null,
            'content' => $validated['content'],
            'rating' => $validated['rating'] ?? 5,
        ]);

        return response()->json([
            'success' => true,
            'feedbackId' => $feedback->feedback_id,
        ]);
    }
}
