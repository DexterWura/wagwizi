<?php

namespace App\Services\Platform;

use DateTimeInterface;

final readonly class TokenResult
{
    public function __construct(
        public bool               $success,
        public ?string            $accessToken = null,
        public ?string            $refreshToken = null,
        public ?DateTimeInterface $expiresAt = null,
        public ?string            $errorMessage = null,
    ) {}

    public static function ok(
        string             $accessToken,
        ?string            $refreshToken = null,
        ?DateTimeInterface $expiresAt = null,
    ): self {
        return new self(
            success:      true,
            accessToken:  $accessToken,
            refreshToken: $refreshToken,
            expiresAt:    $expiresAt,
        );
    }

    public static function fail(string $errorMessage): self
    {
        return new self(
            success:      false,
            errorMessage: $errorMessage,
        );
    }
}
