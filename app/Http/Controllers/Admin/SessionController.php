<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Display all user sessions (admin only)
     */
    public function index(Request $request)
    {
        $query = UserSession::with('user')->orderBy('last_active_at', 'desc');
        
        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by device type
        if ($request->filled('device')) {
            $query->where('device_type', $request->device);
        }
        
        $sessions = $query->paginate(20);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        
        return view('admin.sessions.index', compact('sessions', 'users'));
    }

    /**
     * Terminate a specific session
     */
    public function terminate(UserSession $session)
    {
        $userName = $session->user->name ?? 'Unknown';
        $session->delete();
        
        return back()->with('success', "Session for {$userName} has been terminated.");
    }

    /**
     * Terminate all sessions for a specific user
     */
    public function terminateUser(User $user)
    {
        $count = UserSession::where('user_id', $user->id)->delete();
        
        return back()->with('success', "All {$count} session(s) for {$user->name} have been terminated.");
    }

    /**
     * Terminate all sessions except current user's
     */
    public function terminateAllOthers()
    {
        $count = UserSession::where('user_id', '!=', auth()->id())->delete();
        
        return back()->with('success', "{$count} session(s) have been terminated.");
    }
}
