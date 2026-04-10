<?php

namespace Tests\Unit;

use App\Models\WorkspaceMembership;
use PHPUnit\Framework\TestCase;

class WorkspaceMembershipTest extends TestCase
{
    public function test_is_admin_returns_true_only_for_active_admin(): void
    {
        $admin = new WorkspaceMembership(['role' => 'admin', 'status' => 'active']);
        $member = new WorkspaceMembership(['role' => 'member', 'status' => 'active']);
        $inactiveAdmin = new WorkspaceMembership(['role' => 'admin', 'status' => 'invited']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($member->isAdmin());
        $this->assertFalse($inactiveAdmin->isAdmin());
    }
}

