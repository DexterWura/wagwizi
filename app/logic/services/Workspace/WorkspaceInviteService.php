<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Models\WorkspaceInvite;
use App\Models\WorkspaceMembership;
use App\Services\SocialAccount\SocialAccountLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class WorkspaceInviteService
{
    private const INVITE_TTL_DAYS = 7;

    public function __construct(
        private readonly SocialAccountLimitService $limitService,
    ) {}

    public function createInvite(User $admin, string $email, string $role = 'member'): WorkspaceInvite
    {
        $membership = $admin->activeWorkspaceMembership();
        if ($membership === null || ! $membership->isAdmin()) {
            throw new InvalidArgumentException('Only workspace admins can send invites.');
        }

        $workspace = $membership->workspace;
        if ($workspace === null) {
            throw new InvalidArgumentException('Workspace not found.');
        }

        $plan = $this->limitService->effectivePlan($workspace->owner);
        if ($plan === null || ! (bool) ($plan->includes_workspaces ?? false)) {
            throw new InvalidArgumentException('Your current plan does not include team workspaces.');
        }

        $this->assertWorkspaceMemberCapacity($workspace->id, $plan->max_workspace_members);

        $invite = WorkspaceInvite::create([
            'workspace_id' => $workspace->id,
            'invited_by_user_id' => $admin->id,
            'email' => mb_strtolower(trim($email)),
            'role' => $role === 'admin' ? 'admin' : 'member',
            'status' => 'pending',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(self::INVITE_TTL_DAYS),
        ]);

        return $invite;
    }

    public function acceptInvite(User $user, WorkspaceInvite $invite): void
    {
        if ($invite->status !== 'pending') {
            throw new InvalidArgumentException('This invite is no longer active.');
        }
        if ($invite->expires_at !== null && $invite->expires_at->isPast()) {
            throw new InvalidArgumentException('This invite has expired.');
        }
        if (mb_strtolower($user->email) !== mb_strtolower($invite->email)) {
            throw new InvalidArgumentException('This invite is for a different email address.');
        }

        $owner = $invite->workspace?->owner;
        $plan = $owner ? $this->limitService->effectivePlan($owner) : null;
        if ($plan === null || ! (bool) ($plan->includes_workspaces ?? false)) {
            throw new InvalidArgumentException('This workspace plan no longer supports team members.');
        }

        $this->assertWorkspaceMemberCapacity($invite->workspace_id, $plan->max_workspace_members);

        DB::transaction(function () use ($user, $invite): void {
            WorkspaceMembership::updateOrCreate(
                ['workspace_id' => $invite->workspace_id, 'user_id' => $user->id],
                ['role' => $invite->role, 'status' => 'active']
            );

            $invite->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);
        });
    }

    private function assertWorkspaceMemberCapacity(int $workspaceId, ?int $maxWorkspaceMembers): void
    {
        if ($maxWorkspaceMembers === null) {
            return;
        }

        $activeMembers = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->count();
        $pendingInvites = WorkspaceInvite::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        if (($activeMembers + $pendingInvites) >= $maxWorkspaceMembers) {
            throw new InvalidArgumentException("Workspace member limit reached ({$maxWorkspaceMembers}).");
        }
    }
}

