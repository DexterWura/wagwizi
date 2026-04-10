<?php

namespace Tests\Unit;

use App\Models\Plan;
use PHPUnit\Framework\TestCase;

class PlanWorkspaceConfigTest extends TestCase
{
    public function test_plan_workspace_flags_are_cast_correctly(): void
    {
        $plan = new Plan([
            'includes_workspaces' => 1,
            'max_workspace_members' => '5',
            'max_accounts_per_platform' => '3',
        ]);

        $this->assertTrue($plan->includes_workspaces);
        $this->assertSame(5, $plan->max_workspace_members);
        $this->assertSame(3, $plan->max_accounts_per_platform);
    }
}

