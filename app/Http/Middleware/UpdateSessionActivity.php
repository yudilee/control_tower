<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateSessionActivity
{
    /**
     * Update the user session's last_active_at timestamp on each request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            // Update session activity (throttled to once per minute to reduce DB writes)
            $sessionId = session()->getId();
            $cacheKey = 'session_activity_' . $sessionId;
            
            if (!cache()->has($cacheKey)) {
                UserSession::where('session_id', $sessionId)
                    ->where('user_id', Auth::id())
                    ->update(['last_active_at' => now()]);
                    
                // Cache for 1 minute to avoid updating on every request
                cache()->put($cacheKey, true, 60);
            }
        }

        return $next($request);
    }
}
