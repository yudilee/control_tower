<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get notifications for navbar dropdown (AJAX)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
            
        $unreadCount = Notification::where('user_id', $user->id)
            ->unread()
            ->count();
        
        if ($request->expectsJson()) {
            return response()->json([
                'notifications' => $notifications->map(fn($n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'icon' => $n->icon,
                    'color' => $n->color,
                    'link' => $n->link,
                    'read' => $n->isRead(),
                    'time_ago' => $n->created_at->diffForHumans(),
                ]),
                'unread_count' => $unreadCount,
            ]);
        }
        
        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Mark a notification as read
     */
    public function markRead(Notification $notification)
    {
        // Verify ownership
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }
        
        $notification->markAsRead();
        
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        // Redirect to the link if exists
        if ($notification->link) {
            return redirect($notification->link);
        }
        
        return back();
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);
        
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Delete a notification
     */
    public function destroy(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }
        
        $notification->delete();
        
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        return back()->with('success', 'Notification deleted.');
    }

    /**
     * Clear all notifications
     */
    public function clearAll()
    {
        Notification::where('user_id', auth()->id())->delete();
        
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        
        return back()->with('success', 'All notifications cleared.');
    }
}
