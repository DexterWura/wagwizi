<?php

declare(strict_types=1);

// #region agent log
/**
 * NDJSON debug logger — session 6ca688 (writes project-root debug-6ca688.log).
 */
function agent_log_6ca688(string $location, string $message, array $data = [], string $hypothesisId = ''): void
{
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'debug-6ca688.log';
    $payload = [
        'sessionId' => '6ca688',
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'hypothesisId' => $hypothesisId,
        'runId' => $GLOBALS['agent_log_run_id'] ?? 'pre-fix',
    ];
    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}
// #endregion
