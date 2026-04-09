<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Audit\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditTrailRequestLogger
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        // Ignore noise from static assets and debug endpoints.
        $path = '/' . ltrim($request->path(), '/');
        if (
            str_starts_with($path, '/assets/')
            || str_starts_with($path, '/storage/')
            || str_starts_with($path, '/build/')
            || str_starts_with($path, '/favicon')
        ) {
            return $response;
        }

        $user = $request->user();
        $payload = $request->except([
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'client_secret',
            'secret',
            'authorization',
            'api_key',
        ]);

        $this->auditTrailService->record(
            category: 'request',
            event: 'http_request',
            userId: $user?->id ? (int) $user->id : null,
            request: $request,
            statusCode: $response->getStatusCode(),
            metadata: [
                'duration_ms' => $durationMs,
                'query' => $request->query(),
                'payload' => $payload,
                'is_ajax' => $request->ajax(),
                'is_api' => str_starts_with($path, '/api/'),
            ],
        );

        return $response;
    }
}

