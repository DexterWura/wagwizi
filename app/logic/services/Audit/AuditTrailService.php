<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

final class AuditTrailService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $category,
        string $event,
        ?int $userId = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $statusCode = null,
    ): void {
        try {
            AuditTrail::query()->create([
                'occurred_at' => now(),
                'user_id' => $userId,
                'category' => $category,
                'event' => $event,
                'method' => $request?->method(),
                'path' => $request?->path(),
                'route_name' => $request?->route()?->getName(),
                'status_code' => $statusCode,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? $this->truncate((string) $request->userAgent(), 1024) : null,
                'metadata' => $this->sanitizeMetadata($metadata),
            ]);
        } catch (Throwable) {
            // Auditing must never break user flows.
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'client_secret',
            'authorization',
            'secret',
            'api_key',
            'stripe_secret',
            'paypal_client_secret',
        ];

        foreach ($sensitive as $key) {
            if (Arr::has($metadata, $key)) {
                Arr::set($metadata, $key, '[REDACTED]');
            }
        }

        return $this->truncateMixed($metadata);
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function truncateMixed($value)
    {
        if (is_string($value)) {
            return $this->truncate($value, 1200);
        }
        if (is_array($value)) {
            $out = [];
            $count = 0;
            foreach ($value as $k => $v) {
                $count++;
                if ($count > 60) {
                    $out['__truncated__'] = true;
                    break;
                }
                $out[$k] = $this->truncateMixed($v);
            }

            return $out;
        }

        return $value;
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max) . '...';
    }
}

