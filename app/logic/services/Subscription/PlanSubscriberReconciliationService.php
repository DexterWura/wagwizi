<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\PlatformAiQuotaService;
use App\Services\SocialAccount\AccountLinkingService;
use Illuminate\Support\Collection;

final class PlanSubscriberReconciliationService
{
    /** @var list<string> */
    public const SUBSCRIBER_STATUSES = ['active', 'trialing', 'past_due'];

    public function __construct(
        private readonly AccountLinkingService $accountLinking,
        private readonly PlatformAiQuotaService $platformAiQuota,
    ) {}

    /**
     * Align connected social accounts with the plan definition for every subscriber on this plan.
     */
    public function reconcileSubscribersForPlan(Plan $plan): void
    {
        $userIds = Subscription::query()
            ->where('plan_id', $plan->id)
            ->whereIn('status', self::SUBSCRIBER_STATUSES)
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $userIds->chunk(500)->each(function (Collection $chunk) use ($plan): void {
            $this->reconcileSubscriberChunk($plan, $chunk);
        });
    }

    /**
     * @param  Collection<int, int|string>  $userIdChunk
     */
    private function reconcileSubscriberChunk(Plan $plan, Collection $userIdChunk): void
    {
        $ids = $userIdChunk->map(static fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $usersById = User::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(static fn (User $u): int => (int) $u->id);
        if ($usersById->isEmpty()) {
            return;
        }

        $accountsByUser = SocialAccount::query()
            ->whereIn('user_id', $ids)
            ->active()
            ->orderBy('id')
            ->get()
            ->groupBy(static fn (SocialAccount $a): int => (int) $a->user_id);

        $layoutUsersToInvalidate = [];

        foreach ($ids as $userId) {
            if (! $usersById->has($userId)) {
                continue;
            }

            $accounts = $accountsByUser->get($userId, collect());
            if ($accounts->isEmpty()) {
                continue;
            }

            $toDisconnect = $this->accountsToDisconnectForUser($plan, $accounts);
            if ($toDisconnect->isEmpty()) {
                continue;
            }

            foreach ($this->accountLinking->disconnectAccountsForPlanEnforcement($toDisconnect) as $uid) {
                $layoutUsersToInvalidate[$uid] = true;
            }
        }

        foreach (array_keys($layoutUsersToInvalidate) as $uid) {
            $this->platformAiQuota->invalidateLayoutSummaryCache((int) $uid);
        }
    }

    /**
     * @param  Collection<int, SocialAccount>  $orderedActiveAccounts  ascending by id
     * @return Collection<int, SocialAccount>
     */
    private function accountsToDisconnectForUser(Plan $plan, Collection $orderedActiveAccounts): Collection
    {
        $disconnectIds = [];

        foreach ($orderedActiveAccounts as $account) {
            if (! $plan->allowsPlatform($account->platform)) {
                $disconnectIds[$account->id] = true;
            }
        }

        $kept = $orderedActiveAccounts->filter(
            static fn (SocialAccount $a): bool => ! isset($disconnectIds[$a->id])
        )->values();

        if (! $plan->hasUnlimitedProfiles()) {
            $limit = (int) $plan->max_social_profiles;
            if ($limit >= 1 && $kept->count() > $limit) {
                foreach ($kept->slice($limit) as $account) {
                    $disconnectIds[$account->id] = true;
                }
            }
        }

        $platformCap = $plan->max_accounts_per_platform;
        if ($platformCap !== null && $platformCap >= 1) {
            $kept = $kept->filter(
                static fn (SocialAccount $a): bool => ! isset($disconnectIds[$a->id])
            )->values();
            $kept->groupBy('platform')->each(function (Collection $accounts) use (&$disconnectIds, $platformCap): void {
                if ($accounts->count() <= $platformCap) {
                    return;
                }

                foreach ($accounts->slice($platformCap) as $account) {
                    $disconnectIds[$account->id] = true;
                }
            });
        }

        return $orderedActiveAccounts
            ->filter(static fn (SocialAccount $a): bool => isset($disconnectIds[$a->id]))
            ->values();
    }
}
