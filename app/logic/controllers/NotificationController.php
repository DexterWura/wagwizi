<?php

namespace App\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'type', 'title', 'body', 'read_at', 'created_at']);

        return response()->json([
            'success'       => true,
            'notifications' => $notifications,
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
