<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\V1\CapabilityController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Liveness probe for hosts and load balancers. Returns component status
 * only — no tenant data. Critical components (database, cache) failing
 * yields 503; optional components (queue, AI gateway) degrade the status
 * without failing the probe.
 */
final class HealthController extends CapabilityController
{
    public function __invoke(): JsonResponse
    {
        $checks = (array) config('observability.health.checks', []);

        $database = $this->probeDatabase();
        $cache = $this->probeCache();
        $queue = $checks['queue'] ?? false ? $this->probeQueue() : 'disabled';
        $aiGateway = ($checks['ai_gateway'] ?? false) ? $this->probeAiGateway() : 'disabled';

        $critical = ['database' => $database, 'cache' => $cache];
        $degraded = in_array('down', $critical, true);

        $components = [
            'database' => $database,
            'cache' => $cache,
            'queue' => $queue,
            'ai_gateway' => $aiGateway,
        ];

        $status = $degraded ? 'down' : ($components['queue'] === 'down' || $components['ai_gateway'] === 'unreachable' ? 'degraded' : 'ok');

        return response()->json([
            'data' => [
                'status' => $status,
                'components' => $components,
                'checked_at' => now()->toIso8601String(),
            ],
        ], $degraded ? 503 : 200);
    }

    private function probeDatabase(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function probeCache(): string
    {
        try {
            Cache::put('health-check', 'ok', 10);

            return Cache::get('health-check') === 'ok' ? 'ok' : 'down';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function probeQueue(): string
    {
        try {
            DB::table('jobs')->limit(1)->get();

            return 'ok';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function probeAiGateway(): string
    {
        $url = config('ai.providers.zen.url', '');
        $timeout = config('observability.health.ai_gateway_timeout', 5);

        if (! is_string($url) || $url === '') {
            return 'disabled';
        }

        if (! is_int($timeout)) {
            $timeout = 5;
        }

        try {
            $response = Http::timeout($timeout)
                ->get(rtrim($url, '/').'/models');

            return $response->successful() ? 'ok' : 'unreachable';
        } catch (ConnectionException) {
            return 'unreachable';
        }
    }
}
