<?php

namespace App\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
            ->map(static function (Notification $n): array {
                $data = is_array($n->data) ? $n->data : [];

                return [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'body'       => $n->body,
                    'read_at'    => $n->read_at,
                    'created_at' => $n->created_at,
                    'action_url' => isset($data['action_url']) && is_string($data['action_url'])
                        ? $data['action_url']
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success'       => true,
            'notifications' => $notifications,
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Auth::user()->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'success' => true,
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}
