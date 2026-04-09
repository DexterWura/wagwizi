<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IpBlock;
use App\Services\Audit\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnforceIpBlocks
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!Schema::hasTable('ip_blocks')) {
                return $next($request);
            }

            $ip = (string) ($request->ip() ?? '');
            if ($ip === '') {
                return $next($request);
            }

            $blocked = IpBlock::query()
                ->where('ip_address', $ip)
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($blocked === null) {
                return $next($request);
            }

            $this->auditTrailService->record(
                category: 'security',
                event: 'ip_blocked_request',
                request: $request,
                statusCode: 403,
                metadata: [
                    'ip_block_id' => $blocked->id,
                    'reason' => (string) ($blocked->reason ?? ''),
                ],
            );

            abort(403, 'Access from this IP address has been blocked.');
        } catch (\Throwable) {
            return $next($request);
        }
    }
}

