<?php

namespace App\Services\Post;

/**
 * Groups provider errors for logging, retries, and future UX (reconnect vs retry).
 */
final class PublishErrorClassifier
{
    public const REAUTH = 'reauth';

    public const RETRYABLE = 'retryable';

    public const PERMANENT = 'permanent';

    public const UNKNOWN = 'unknown';

    public static function classify(?int $errorCode, ?string $errorMessage): string
    {
        if (self::matchesAuthFailure($errorCode, $errorMessage)) {
            return self::REAUTH;
        }
        if (self::matchesPermanentProviderFailure($errorCode, $errorMessage)) {
            return self::PERMANENT;
        }
        if (self::matchesRetryable($errorCode, $errorMessage)) {
            return self::RETRYABLE;
        }

        return self::UNKNOWN;
    }

    public static function matchesAuthFailure(?int $errorCode, ?string $errorMessage): bool
    {
        if (in_array($errorCode, [401, 403], true)) {
            return true;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        $signals = [
            'invalid token',
            'invalid_token',
            'invalid access token',
            'invalid.access.token',
            'access token invalid',
            'token expired',
            'expired token',
            'token rejected',
            'unauthorized',
            'permission',
            'invalid oauth',
            'authentication',
            'forbidden',
            'access denied',
        ];

        foreach ($signals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }

    public static function matchesPermanentProviderFailure(?int $errorCode, ?string $errorMessage): bool
    {
        if ($errorCode === 402) {
            return true;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        $signals = [
            'creditsdepleted',
            'credit',
            'quota exceeded',
            'insufficient balance',
            'billing',
            'payment required',
            'upgrade your plan',
        ];

        foreach ($signals as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }

    public static function matchesRetryable(?int $errorCode, ?string $errorMessage): bool
    {
        if (in_array($errorCode, [408, 425, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        $msg = strtolower((string) $errorMessage);
        if ($msg === '') {
            return false;
        }

        foreach (['timeout', 'timed out', 'rate limit', 'temporarily unavailable', 'try again', 'connection reset', 'connection refused'] as $signal) {
            if (str_contains($msg, $signal)) {
                return true;
            }
        }

        return false;
    }
}
