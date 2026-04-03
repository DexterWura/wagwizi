<?php

namespace App\Services\Admin;

use App\Models\PaymentTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class PaymentTransactionListService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $q = PaymentTransaction::query()->with(['user:id,name,email', 'plan:id,name']);

        $status = $request->input('status');
        if (is_string($status) && $status !== '' && in_array($status, ['pending', 'completed', 'failed'], true)) {
            $q->where('status', $status);
        }

        $gateway = $request->input('gateway');
        if (is_string($gateway) && $gateway !== '' && in_array($gateway, ['paynow', 'pesepay'], true)) {
            $q->where('gateway', $gateway);
        }

        $from = $request->input('date_from');
        if (is_string($from) && $from !== '') {
            $q->whereDate('created_at', '>=', $from);
        }

        $to = $request->input('date_to');
        if (is_string($to) && $to !== '') {
            $q->whereDate('created_at', '<=', $to);
        }

        $search = trim((string) $request->input('q'));
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $q->where(function ($sub) use ($like, $search) {
                $sub->where('reference', 'like', $like)
                    ->orWhere('paynow_reference', 'like', $like);
                if (ctype_digit($search)) {
                    $sub->orWhere('user_id', (int) $search);
                }
                $sub->orWhereHas('user', function ($uq) use ($like) {
                    $uq->where('email', 'like', $like)->orWhere('name', 'like', $like);
                });
            });
        }

        return $q->orderByDesc('id')->paginate(30)->withQueryString();
    }
}
