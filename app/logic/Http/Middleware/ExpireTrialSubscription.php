<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionPeriodService;
use App\Services\Subscription\SubscriptionTrialService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class ExpireTrialSubscription
{
    private const CHECK_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly SubscriptionTrialService $trialService,
        private readonly SubscriptionPeriodService $periodService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null) {
            $cacheKey = "sub_expiry_checked:{$user->id}";
            if (! Cache::has($cacheKey)) {
                $this->trialService->expireTrialingIfDue($user);
                $this->periodService->expirePaidActivePeriodIfDue($user);
                Cache::put($cacheKey, true, self::CHECK_INTERVAL_SECONDS);
            }
        }

        return $next($request);
    }
}
