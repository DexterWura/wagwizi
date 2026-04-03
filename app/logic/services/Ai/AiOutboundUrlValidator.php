<?php

declare(strict_types=1);

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * Reduces SSRF risk when the server calls user-supplied BYOK base URLs.
 */
final class AiOutboundUrlValidator
{
    /** @var list<string> */
    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'metadata.google.internal',
        'metadata.google.com',
        'instance-data',
    ];

    public function assertSafeForServerSideHttp(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Invalid API base URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $allowHttp = app()->environment('local');
        if ($scheme === 'https') {
            // ok
        } elseif ($scheme === 'http' && $allowHttp) {
            // ok in local only
        } else {
            throw new InvalidArgumentException('Use HTTPS for the custom API base URL'.($allowHttp ? ' (HTTP is allowed only in the local environment).' : '.'));
        }

        $host = strtolower((string) $parts['host']);
        $hostForIpCheck = $host;
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $hostForIpCheck = substr($host, 1, -1);
        }

        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === strtolower($blocked) || $hostForIpCheck === strtolower($blocked)) {
                throw new InvalidArgumentException('This API host is not allowed.');
            }
        }

        if (filter_var($hostForIpCheck, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicRoutableIp($hostForIpCheck)) {
                throw new InvalidArgumentException('API URL must not target a private or reserved address.');
            }

            return;
        }

        $records = @gethostbynamel($host);
        if ($records === false || $records === []) {
            throw new InvalidArgumentException('API host could not be resolved.');
        }

        foreach ($records as $ip) {
            if (! $this->isPublicRoutableIp($ip)) {
                throw new InvalidArgumentException('API host resolves to a disallowed network address.');
            }
        }
    }

    private function isPublicRoutableIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
