<?php

namespace App\Controllers;

use App\Services\Cron\CronSecretResolver;
use App\Services\Cron\CronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{
    public function __construct(
        private readonly CronService $cronService,
        private readonly CronSecretResolver $cronSecretResolver,
    ) {}

    public function run(Request $request): JsonResponse
    {
        $secret = $request->header('X-Cron-Secret') ?? $request->input('token');

        $expected = $this->cronSecretResolver->get();

        if (empty($expected) || !hash_equals($expected, (string) $secret)) {
            Log::warning('Cron endpoint hit with invalid secret', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $results = $this->cronService->runDueTasks();

        $ran    = collect($results)->where('status', '!=', 'skipped')->count();
        $failed = collect($results)->where('status', 'failed')->count();

        Log::info('Cron run completed', ['ran' => $ran, 'failed' => $failed]);

        return response()->json([
            'ran'     => $ran,
            'failed'  => $failed,
            'tasks'   => $results,
        ]);
    }
}
