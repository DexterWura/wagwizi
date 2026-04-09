<?php

namespace Tests\Unit;

use App\Services\Workflow\WorkflowTemplateService;
use PHPUnit\Framework\TestCase;

final class WorkflowTemplateServiceTest extends TestCase
{
    public function test_templates_contain_expected_mvp_use_cases(): void
    {
        $templates = (new WorkflowTemplateService())->templates();

        $keys = array_map(static fn (array $t): string => (string) ($t['key'] ?? ''), $templates);

        $this->assertContains('scheduled_ai_autopilot', $keys);
        $this->assertContains('event_news_to_social', $keys);
        $this->assertContains('approval_before_publish', $keys);
        $this->assertContains('retry_after_delay', $keys);
        $this->assertContains('campaign_batcher', $keys);
    }

    public function test_every_template_contains_graph_nodes(): void
    {
        $templates = (new WorkflowTemplateService())->templates();

        foreach ($templates as $template) {
            $graph = $template['graph'] ?? null;
            $nodes = is_array($graph) ? ($graph['nodes'] ?? null) : null;
            $this->assertIsArray($nodes);
            $this->assertNotEmpty($nodes);
        }
    }
}

