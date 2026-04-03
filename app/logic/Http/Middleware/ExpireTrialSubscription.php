<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionPeriodService;
use App\Services\Subscription\SubscriptionTrialService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ExpireTrialSubscription
{
    public function __construct(
        private readonly SubscriptionTrialService $trialService,
        private readonly SubscriptionPeriodService $periodService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null) {
            $this->trialService->expireTrialingIfDue($user);
            $this->periodService->expirePaidActivePeriodIfDue($user);
        }

        return $next($request);
    }
}
