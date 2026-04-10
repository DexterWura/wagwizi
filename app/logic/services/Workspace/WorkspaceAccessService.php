<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use InvalidArgumentException;

final class WorkspaceAccessService
{
    public function activeMembership(User $user): ?WorkspaceMembership
    {
        return $user->activeWorkspaceMembership();
    }

    public function activeWorkspace(User $user): ?Workspace
    {
        return $user->activeWorkspace();
    }

    public function ensureAdmin(User $user): WorkspaceMembership
    {
        $membership = $this->activeMembership($user);
        if ($membership === null || ! $membership->isAdmin()) {
            throw new InvalidArgumentException('Only workspace admins can manage team members.');
        }

        return $membership;
    }
}

