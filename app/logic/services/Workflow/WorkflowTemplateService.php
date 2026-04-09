<?php

declare(strict_types=1);

namespace App\Services\Workflow;

final class WorkflowTemplateService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return [
            [
                'key' => 'platform_targeted_distribution',
                'name' => 'Platform Targeted Distribution',
                'description' => 'Compose once, then include only selected platforms before publish.',
                'trigger_type' => 'manual',
                'trigger_config' => [],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.manual', 'config' => []],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                        ['id' => 'select1', 'type' => 'utility.select_platforms', 'config' => ['platforms' => ['linkedin', 'twitter']]],
                        ['id' => 'publish1', 'type' => 'action.publish_post', 'config' => []],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'compose1'],
                        ['from' => 'compose1', 'to' => 'select1'],
                        ['from' => 'select1', 'to' => 'publish1'],
                    ],
                ],
            ],
            [
                'key' => 'scheduled_ai_autopilot',
                'name' => 'Scheduled AI Autopilot',
                'description' => 'On schedule, generate a caption with AI then publish.',
                'trigger_type' => 'schedule',
                'trigger_config' => ['interval_minutes' => 60],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.schedule', 'config' => []],
                        ['id' => 'set1', 'type' => 'utility.set_variables', 'config' => ['variables' => ['draft' => 'Share your update here']]],
                        ['id' => 'ai1', 'type' => 'ai.generate_caption', 'config' => ['instruction' => 'Rewrite this to sound engaging and concise.']],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                        ['id' => 'publish1', 'type' => 'action.publish_post', 'config' => []],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'set1'],
                        ['from' => 'set1', 'to' => 'ai1'],
                        ['from' => 'ai1', 'to' => 'compose1'],
                        ['from' => 'compose1', 'to' => 'publish1'],
                    ],
                ],
            ],
            [
                'key' => 'event_news_to_social',
                'name' => 'Event: News to Social',
                'description' => 'On event input, summarize with AI and publish.',
                'trigger_type' => 'event',
                'trigger_config' => ['event_key' => 'news_item.created'],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.event', 'config' => []],
                        ['id' => 'ai1', 'type' => 'ai.generate_caption', 'config' => ['instruction' => 'Summarize this event into a short social post.']],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                        ['id' => 'publish1', 'type' => 'action.publish_post', 'config' => []],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'ai1'],
                        ['from' => 'ai1', 'to' => 'compose1'],
                        ['from' => 'compose1', 'to' => 'publish1'],
                    ],
                ],
            ],
            [
                'key' => 'approval_before_publish',
                'name' => 'Approval Before Publish',
                'description' => 'Create draft on event and wait for manual publish.',
                'trigger_type' => 'event',
                'trigger_config' => ['event_key' => 'campaign.ready'],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.event', 'config' => []],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'compose1'],
                    ],
                ],
            ],
            [
                'key' => 'retry_after_delay',
                'name' => 'Retry After Delay',
                'description' => 'Delay and retry publish flow.',
                'trigger_type' => 'manual',
                'trigger_config' => [],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.manual', 'config' => []],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                        ['id' => 'publish1', 'type' => 'action.publish_post', 'config' => []],
                        ['id' => 'delay1', 'type' => 'utility.delay', 'config' => ['seconds' => 30]],
                        ['id' => 'publish2', 'type' => 'action.publish_post', 'config' => []],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'compose1'],
                        ['from' => 'compose1', 'to' => 'publish1'],
                        ['from' => 'publish1', 'to' => 'delay1'],
                        ['from' => 'delay1', 'to' => 'publish2'],
                    ],
                ],
            ],
            [
                'key' => 'campaign_batcher',
                'name' => 'Campaign Batcher',
                'description' => 'Publish a timed sequence with optional AI rewrite.',
                'trigger_type' => 'schedule',
                'trigger_config' => ['interval_minutes' => 1440],
                'graph' => [
                    'nodes' => [
                        ['id' => 'trigger1', 'type' => 'trigger.schedule', 'config' => []],
                        ['id' => 'set1', 'type' => 'utility.set_variables', 'config' => ['variables' => ['draft' => 'Campaign launch update']]],
                        ['id' => 'ai1', 'type' => 'ai.generate_caption', 'config' => ['instruction' => 'Turn this into an energetic launch post with CTA.']],
                        ['id' => 'compose1', 'type' => 'action.compose_post', 'config' => ['platform_account_ids' => [], 'audience' => 'everyone']],
                        ['id' => 'publish1', 'type' => 'action.publish_post', 'config' => []],
                        ['id' => 'delay1', 'type' => 'utility.delay', 'config' => ['seconds' => 60]],
                        ['id' => 'publish2', 'type' => 'action.publish_post', 'config' => []],
                    ],
                    'edges' => [
                        ['from' => 'trigger1', 'to' => 'set1'],
                        ['from' => 'set1', 'to' => 'ai1'],
                        ['from' => 'ai1', 'to' => 'compose1'],
                        ['from' => 'compose1', 'to' => 'publish1'],
                        ['from' => 'publish1', 'to' => 'delay1'],
                        ['from' => 'delay1', 'to' => 'publish2'],
                    ],
                ],
            ],
        ];
    }
}

