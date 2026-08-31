<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateJwtSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = env('JWT_SECRET', 'deceit_offline_super_secret_jwt_key_2026_998877665544332211');

        $headerSecret = $request->header('x-jwt-secret') 
            ?? $request->header('x-secret') 
            ?? $request->header('x-api-key') 
            ?? $request->header('Authorization');

        if ($headerSecret && str_starts_with($headerSecret, 'Bearer ')) {
            $headerSecret = substr($headerSecret, 7);
        }

        if ($headerSecret === $expectedSecret) {
            return $next($request);
        }

        // If no direct secret header, allow session or pass through if route is public
        if ($request->user() || $request->hasSession()) {
            return $next($request);
        }

        return response()->json([
            'error' => 'UNAUTHORIZED',
            'message' => 'رمز التحقق غير صحيح أو مفقود.'
        ], 401);
    }
}
