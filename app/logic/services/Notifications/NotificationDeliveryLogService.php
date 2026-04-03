<?php

namespace App\Services\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class NotificationDeliveryLogService
{
    public function paginateFiltered(Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $q = NotificationDelivery::query()->with('user:id,name,email')->orderByDesc('created_at');

        if ($channel = $request->input('channel')) {
            $q->where('channel', $channel);
        }

        if ($templateKey = $request->input('template_key')) {
            $q->where('template_key', $templateKey);
        }

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }

        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($search = trim((string) $request->input('user_search'))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('to_address', 'like', "%{$search}%")
                    ->orWhere('to_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $q->paginate($perPage)->appends($request->query());
    }
}
