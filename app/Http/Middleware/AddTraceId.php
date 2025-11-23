<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request Tracing Middleware
 *
 * Generates a unique incremental trace ID for each request to enable end-to-end tracking
 * across logs, transactions, webhooks, and external API calls.
 *
 * Format: TRC-YYYYMMDD-SERVERID-NNNNNN (e.g., TRC-20251123-A1B2C3D4-000001)
 *
 * The SERVERID ensures global uniqueness in distributed environments (multiple servers/containers).
 *
 */
class AddTraceId
{
    private const CACHE_KEY_PREFIX = 'trace_counter_';
    private const TRACE_PREFIX = 'TRC-';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $this->generateTraceId();
        $request->attributes->set('trace_id', $traceId);
        config(['app.trace_id' => $traceId]);
        $response = $next($request);
        $response->headers->set('X-Trace-ID', $traceId);

        return $response;
    }

    /**
     * Generate globally unique incremental trace ID for distributed systems.
     * Format: TRC-YYYYMMDD-SERVERID-NNNNNN
     * Example: TRC-20251123-A1B2C3D4-000001
     */
    private function generateTraceId(): string
    {
        $date = now()->format('Ymd');
        $serverId = $this->getServerId();
        $cacheKey = self::CACHE_KEY_PREFIX . $date . '_' . $serverId;

        // Atomic increment with 24-hour TTL (resets daily per server)
        $counter = Cache::increment($cacheKey, 1);

        if ($counter === 1) {
            // Set expiry on first creation
            Cache::put($cacheKey, 1, now()->endOfDay());
        }

        // Format: TRC-20251123-A1B2C3D4-000001
        return self::TRACE_PREFIX . $date . '-' . $serverId . '-' . str_pad($counter, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get unique server/container identifier.
     *
     * Checks multiple sources in order of preference:
     * 1. SERVER_ID config/env variable (manual configuration for production)
     * 2. HOSTNAME environment variable (Kubernetes pod name, Docker container name)
     * 3. Machine ID from /etc/machine-id (Linux systems)
     * 4. Fallback to hostname hash
     *
     * @return string 8-character uppercase identifier
     */
    private function getServerId(): string
    {
        $serverId = config('app.server_id') ?? env('SERVER_ID');
        if ($serverId) {
            return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $serverId), 0, 8));
        }

        $hostname = env('HOSTNAME') ?? gethostname();
        if ($hostname && $hostname !== 'localhost' && $hostname !== false) {
            return strtoupper(substr(md5($hostname), 0, 8));
        }

        if (file_exists('/etc/machine-id')) {
            $machineId = @file_get_contents('/etc/machine-id');
            if ($machineId) {
                $machineId = trim($machineId);
                return strtoupper(substr($machineId, 0, 8));
            }
        }

        return strtoupper(substr(md5($hostname ?: 'default'), 0, 8));
    }
}
