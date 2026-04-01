<?php

namespace App\Services\Platform;

final readonly class PublishResult
{
    public function __construct(
        public bool    $success,
        public ?string $platformPostId = null,
        public ?string $url = null,
        public ?string $errorMessage = null,
        public ?int    $errorCode = null,
    ) {}

    public static function ok(string $platformPostId, ?string $url = null): self
    {
        return new self(
            success:        true,
            platformPostId: $platformPostId,
            url:            $url,
        );
    }

    public static function fail(string $errorMessage, ?int $errorCode = null): self
    {
        return new self(
            success:      false,
            errorMessage: $errorMessage,
            errorCode:    $errorCode,
        );
    }
}
