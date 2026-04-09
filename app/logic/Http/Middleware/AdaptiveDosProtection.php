<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IpBlock;
use App\Services\Audit\AuditTrailService;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class AdaptiveDosProtection
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) ($request->ip() ?? '');
        if ($ip === '') {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');
        $isApi = str_starts_with($path, '/api/');
        $isAuthEndpoint = in_array($path, ['/login', '/signup', '/forgot-password', '/reset-password'], true)
            || str_starts_with($path, '/auth/');

        $bucket = $isAuthEndpoint ? 'auth' : ($isApi ? 'api' : 'web');
        $maxAttempts = match ($bucket) {
            'auth' => 20,
            'api' => 180,
            default => 240,
        };
        $decaySeconds = 60;

        $baseKey = "dos:{$bucket}:{$ip}";
        if ($this->rateLimiter->tooManyAttempts($baseKey, $maxAttempts)) {
            $retryAfter = $this->rateLimiter->availableIn($baseKey);
            $this->registerAbuseAndMaybeBlock($request, $ip, $bucket, $retryAfter);

            if ($isApi) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                    'retry_after' => $retryAfter,
                ], 429, ['Retry-After' => (string) $retryAfter]);
            }

            return response('Too many requests. Please try again shortly.', 429, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        $this->rateLimiter->hit($baseKey, $decaySeconds);

        return $next($request);
    }

    private function registerAbuseAndMaybeBlock(Request $request, string $ip, string $bucket, int $retryAfter): void
    {
        $abuseKey = "dos_abuse:{$bucket}:{$ip}";
        $this->rateLimiter->hit($abuseKey, 600);
        $abuseAttempts = $this->rateLimiter->attempts($abuseKey);

        $this->auditTrailService->record(
            category: 'security',
            event: 'dos_rate_limited',
            request: $request,
            statusCode: 429,
            metadata: [
                'bucket' => $bucket,
                'retry_after' => $retryAfter,
                'abuse_attempts' => $abuseAttempts,
            ],
        );

        // Escalate to temporary IP block after repeated rate-limit violations.
        if ($abuseAttempts < 8) {
            return;
        }

        if (!Schema::hasTable('ip_blocks')) {
            return;
        }

        $expiresAt = now()->addMinutes(30);
        $row = IpBlock::query()->updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => 'Auto block: repeated rate-limit violations (' . $bucket . ')',
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]
        );

        $this->auditTrailService->record(
            category: 'security',
            event: 'dos_auto_block',
            request: $request,
            statusCode: 403,
            metadata: [
                'ip_block_id' => $row->id,
                'bucket' => $bucket,
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
        );
    }
}

