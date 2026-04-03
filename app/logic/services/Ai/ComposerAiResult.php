<?php

declare(strict_types=1);

namespace App\Services\Ai;

final class ComposerAiResult
{
    public function __construct(
        public readonly string $reply,
        public readonly int $totalTokens,
        public readonly string $billingSource,
    ) {}
}
