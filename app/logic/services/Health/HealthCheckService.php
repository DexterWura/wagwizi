<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheckService
{
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
        ];

        $allHealthy = !in_array(false, $checks, true);

        if (!$allHealthy) {
            Log::error('Health check failed', $checks);
        }

        return [
            'healthy' => $allHealthy,
            'checks'  => $checks,
        ];
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, true, 10);
            $result = Cache::get($key) === true;
            Cache::forget($key);
            return $result;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $path = storage_path('app/.health_check');
            $written = file_put_contents($path, 'ok');
            $read = file_get_contents($path);
            @unlink($path);
            return $written !== false && $read === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }
}
