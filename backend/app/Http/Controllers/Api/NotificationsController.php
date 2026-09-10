<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage <= 0) $perPage = 20;
        if ($perPage > 50) $perPage = 50;

        $notifications = $user->notifications()->orderByDesc('created_at')->paginate($perPage);
        $unread = $user->unreadNotifications()->count();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
            'unread_count' => $unread,
        ]);
    }

    public function readOne(Request $request, string $id)
    {
        $user = $request->user();
        $n = $user->notifications()->where('id', $id)->first();
        if (!$n) {
            return response()->json(['message' => 'Notification not found or already deleted'], 404);
        }
        if (is_null($n->read_at)) {
            $n->markAsRead();
        }
        $unread = $user->unreadNotifications()->count();
        return response()->json(['message' => 'Marked as read', 'unread_count' => $unread]);
    }

    public function readAll(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read', 'unread_count' => 0]);
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return response()->json(['message' => 'Notification not found in your inbox'], 404);
        }
        return response()->json($notification);
    }

    public function deleteOne(Request $request, string $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        if (!$notification) {
            return response()->json(['message' => 'Notification not found or already deleted'], 404);
        }
        $notification->delete();
        $unread = $user->unreadNotifications()->count();
        return response()->json(['message' => 'Notification deleted successfully', 'unread_count' => $unread]);
    }

    public function deleteAll(Request $request)
    {
        $user = $request->user();
        $user->notifications()->delete();
        return response()->json(['message' => 'All notifications deleted', 'unread_count' => 0]);
    }
}
