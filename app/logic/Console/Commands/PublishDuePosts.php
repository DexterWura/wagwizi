<?php

namespace App\Console\Commands;

use App\Services\Post\PostPublishingService;
use Illuminate\Console\Command;

class PublishDuePosts extends Command
{
    protected $signature   = 'posts:publish-due';
    protected $description = 'Find and dispatch publishing jobs for all posts that are due';

    public function handle(PostPublishingService $publishingService): int
    {
        $dispatched = $publishingService->publishDuePosts();

        $this->info("Dispatched {$dispatched} post(s) for publishing.");

        return self::SUCCESS;
    }
}
