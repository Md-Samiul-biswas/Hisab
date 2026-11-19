<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AppAuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = AppAuthController::getAuthenticatedUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'error' => 'অননুমোদিত এক্সেস। অনুগ্রহ করে লগইন করুন।',
            ], 401);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
